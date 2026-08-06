<?php

namespace App\Http\Controllers\Api\Sales;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\sls_do_main;
use App\Models\sls_ship_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shipping Order — one vehicle, one trip, the deliveries it carries (PRD §5.7).
 *
 * This is what the gate logs and what the driver carries. Dispatching stamps
 * the departure and marks the deliveries as shipped, so the moment goods leave
 * the yard is recorded once, on the document that records it in real life.
 */
class ShippingOrderController extends Controller
{
    private array $with = ['detail.deliveryOrder.so.cus', 'carrier'];

    public function index(Request $request)
    {
        $rows = sls_ship_main::with(['carrier'])->withCount('detail')
            ->when($request->query('q'), fn ($q, $s) => $q->where('code', 'like', "%{$s}%")
                ->orWhere('vehicle_no', 'like', "%{$s}%"))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        return ApiResponse::item(sls_ship_main::with($this->with)->findOrFail($id));
    }

    /**
     * Deliveries that can still be loaded: prepared or already shipped, and not
     * already riding on another truck.
     */
    public function availableDos(Request $request)
    {
        $taken = DB::table('sls_ship_det as d')
            ->join('sls_ship_main as m', 'm.id', '=', 'd.main_id')
            ->where('m.status', '<>', 'CANCELLED')
            ->when($request->query('ship_id'), fn ($q, $id) => $q->where('m.id', '<>', $id))
            ->pluck('d.do_id');

        /*
         * Anything not yet acknowledged by the customer can still be assigned a
         * truck — including deliveries recorded as sent before shipping orders
         * existed, which otherwise could never be given the vehicle that carried
         * them.
         */
        $rows = sls_do_main::with('so.cus')->withCount('detail')
            /*
             * DO yang sudah diterima pelanggan tidak boleh dimuat ke truk lagi.
             * "DELIVERED" sempat ada di daftar ini sebagai penambal status
             * karangan di data demo — menambal di sini justru menyembunyikan
             * bahwa kosakata statusnya sendiri yang keliru.
             */
            ->whereIn('status', ['DRAFT', 'SHIPPED'])
            ->whereNotIn('id', $taken)
            ->orderByDesc('id')->limit(200)->get();

        return ApiResponse::collection($rows->map(fn ($d) => [
            'id' => $d->id,
            'code' => $d->code,
            'date' => $d->date,
            'status' => $d->status,
            'customer' => $d->so?->cus?->company_n,
            'lines' => $d->detail_count,
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->validateShip($request);

        $ship = DB::transaction(function () use ($data, $request) {
            $ship = sls_ship_main::create([
                'code' => app(NumberingService::class)->next('SHIP', 'SO-SHIP'),
                'date' => $data['date'],
                'carrier_id' => $data['carrier_id'] ?? null,
                'vehicle_no' => $data['vehicle_no'] ?? null,
                'driver' => $data['driver'] ?? null,
                'driver_phone' => $data['driver_phone'] ?? null,
                'destination' => $data['destination'] ?? null,
                'plan_depart' => $data['plan_depart'] ?? null,
                'note' => $data['note'] ?? null,
                'user_id' => $request->user()->id,
                'status' => 'DRAFT',
            ]);
            $this->syncDos($ship, $data['do_ids']);
            AuditLogger::record($request, "Create Shipping Order {$ship->code}", $ship->code);

            return $ship;
        });

        return ApiResponse::item($ship->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $ship = sls_ship_main::findOrFail($id);
        $this->assertDraft($ship);
        $data = $this->validateShip($request);

        DB::transaction(function () use ($ship, $data, $request) {
            $ship->update([
                'date' => $data['date'],
                'carrier_id' => $data['carrier_id'] ?? null,
                'vehicle_no' => $data['vehicle_no'] ?? null,
                'driver' => $data['driver'] ?? null,
                'driver_phone' => $data['driver_phone'] ?? null,
                'destination' => $data['destination'] ?? null,
                'plan_depart' => $data['plan_depart'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
            $ship->detail()->delete();
            $this->syncDos($ship, $data['do_ids']);
            AuditLogger::record($request, "Update Shipping Order {$ship->code}", $ship->code);
        });

        return ApiResponse::item($ship->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $ship = sls_ship_main::findOrFail($id);
        $this->assertDraft($ship);

        DB::transaction(function () use ($ship, $request) {
            $ship->detail()->delete();
            $ship->delete();
            AuditLogger::record($request, "Delete Shipping Order {$ship->code}", $ship->code);
        });

        return ApiResponse::item(['message' => 'Shipping order dihapus.']);
    }

    /**
     * The truck leaves.
     *
     * Deliveries still in draft are marked shipped here rather than separately:
     * the goods physically left, and a delivery order that still says DRAFT
     * after its truck has gone is a lie the warehouse has to live with.
     */
    public function dispatch(Request $request, int $id)
    {
        $ship = sls_ship_main::with('detail')->findOrFail($id);
        $this->assertDraft($ship);

        if ($ship->detail->isEmpty()) {
            throw BizException::make('SHIP_EMPTY', 'Shipping order tanpa DO tidak dapat diberangkatkan.');
        }
        if (! $ship->vehicle_no) {
            throw BizException::make('SHIP_NO_VEHICLE', 'Nomor kendaraan harus diisi sebelum berangkat.');
        }

        /*
         * The stock move belongs to the Delivery Order, not here. A truck that
         * leaves carrying goods the FG warehouse still thinks it holds is how
         * stock and reality part company, so the trip is refused until each
         * delivery has actually been issued.
         */
        $stillDraft = sls_do_main::whereIn('id', $ship->detail->pluck('do_id'))
            ->where('status', 'DRAFT')->pluck('code');

        if ($stillDraft->isNotEmpty()) {
            throw BizException::make(
                'SHIP_DO_DRAFT',
                'DO berikut belum dikirim dari gudang FG (stok belum keluar): '.$stillDraft->implode(', ')
                .'. Kirim DO-nya dulu agar stok dan jurnal ikut bergerak.'
            );
        }

        $ship->update(['status' => 'DISPATCHED', 'departed_at' => now()]);
        AuditLogger::record($request, "Berangkatkan Shipping Order {$ship->code}", $ship->code);

        return ApiResponse::item($ship->fresh()->load($this->with));
    }

    /** Arrived: the customer has the goods. */
    public function deliver(Request $request, int $id)
    {
        $ship = sls_ship_main::with('detail')->findOrFail($id);

        if ($ship->status !== 'DISPATCHED') {
            throw BizException::make('SHIP_STATE', 'Hanya shipping order yang sudah berangkat yang dapat ditandai tiba.');
        }

        DB::transaction(function () use ($ship, $request) {
            $ship->update(['status' => 'DELIVERED', 'arrived_at' => now()]);

            // Arrival is the customer's receipt, so the deliveries it carried
            // follow it — that is what "RECEIVED" means on a DO.
            sls_do_main::whereIn('id', $ship->detail->pluck('do_id'))
                ->where('status', 'SHIPPED')
                ->update(['status' => 'RECEIVED']);

            AuditLogger::record($request, "Shipping Order {$ship->code} tiba di tujuan", $ship->code);
        });

        return ApiResponse::item($ship->fresh()->load($this->with));
    }

    public function cancel(Request $request, int $id)
    {
        $ship = sls_ship_main::findOrFail($id);

        if ($ship->status === 'DELIVERED') {
            throw BizException::make('SHIP_STATE', 'Shipping order yang sudah tiba tidak dapat dibatalkan.');
        }

        $ship->update(['status' => 'CANCELLED']);
        AuditLogger::record($request, "Batalkan Shipping Order {$ship->code}", $ship->code);

        return ApiResponse::item($ship->load($this->with));
    }

    /* ---------------- helpers ---------------- */

    private function assertDraft(sls_ship_main $ship): void
    {
        if ($ship->status !== 'DRAFT') {
            throw BizException::make('SHIP_LOCKED', 'Shipping order yang sudah berangkat tidak dapat diubah.');
        }
    }

    private function syncDos(sls_ship_main $ship, array $doIds): void
    {
        foreach (array_unique($doIds) as $doId) {
            $taken = DB::table('sls_ship_det as d')
                ->join('sls_ship_main as m', 'm.id', '=', 'd.main_id')
                ->where('d.do_id', $doId)
                ->where('m.id', '<>', $ship->id)
                ->where('m.status', '<>', 'CANCELLED')
                ->value('m.code');

            if ($taken) {
                throw BizException::make('SHIP_DO_TAKEN', "DO #{$doId} sudah diangkut shipping order {$taken}.");
            }

            $ship->detail()->create(['do_id' => $doId]);
        }
    }

    private function validateShip(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'carrier_id' => ['nullable', 'integer', 'exists:m_contacts,id'],
            'vehicle_no' => ['nullable', 'string', 'max:20'],
            'driver' => ['nullable', 'string', 'max:60'],
            'driver_phone' => ['nullable', 'string', 'max:25'],
            'destination' => ['nullable', 'string', 'max:200'],
            'plan_depart' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:300'],
            'do_ids' => ['required', 'array', 'min:1'],
            'do_ids.*' => ['integer', 'exists:sls_do_main,id'],
        ]);
    }
}
