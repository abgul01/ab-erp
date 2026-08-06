<?php

namespace App\Http\Controllers\Api\Sales;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\sls_do_main;
use App\Models\sls_pack_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Packing List — how one delivery is divided into boxes (PRD §5.7).
 *
 * A receiving bay counts boxes, not order lines, so the document has to say
 * which box holds what and what it weighs. The one rule that matters: the boxes
 * must add up to the delivery, no more and no less. A packing list that packs
 * more than was sold is how a shipment leaves with goods nobody invoiced.
 */
class PackingListController extends Controller
{
    private array $with = ['detail.item', 'deliveryOrder.so.cus'];

    public function index(Request $request)
    {
        $rows = sls_pack_main::with(['deliveryOrder'])->withCount('detail')
            ->when($request->query('q'), fn ($q, $s) => $q->where('code', 'like', "%{$s}%"))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        return ApiResponse::item(sls_pack_main::with($this->with)->findOrFail($id));
    }

    /**
     * The delivery's lines with how much of each is already in a box.
     *
     * Packing is usually done in more than one sitting, so the screen has to
     * show what is left rather than the original quantity.
     */
    public function doLines(int $doId)
    {
        $do = sls_do_main::with('detail.item')->findOrFail($doId);
        $packed = $this->packedByLine($doId);

        return ApiResponse::item([
            'do_id' => $do->id,
            'do_code' => $do->code,
            'status' => $do->status,
            'lines' => $do->detail->map(fn ($d) => [
                'do_detail_id' => $d->id,
                'item_id' => $d->item_id,
                'item_code' => $d->item?->code,
                'part_name' => $d->item?->part_name,
                'unit_weight' => (float) ($d->item?->weight ?? 0),
                'qty' => (int) $d->qty,
                'qty_packed' => (int) ($packed[$d->id] ?? 0),
                'outstanding' => max(0, (int) $d->qty - (int) ($packed[$d->id] ?? 0)),
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePack($request);
        $this->assertWithinDelivery($data['do_id'], $data['lines'], null);

        $pack = DB::transaction(function () use ($data, $request) {
            $pack = sls_pack_main::create([
                'code' => app(NumberingService::class)->next('PACK', 'PL'),
                'date' => $data['date'],
                'do_id' => $data['do_id'],
                'note' => $data['note'] ?? null,
                'user_id' => $request->user()->id,
                'status' => 'DRAFT',
            ]);
            $this->syncLines($pack, $data['lines']);
            AuditLogger::record($request, "Create Packing List {$pack->code}", $pack->code);

            return $pack;
        });

        return ApiResponse::item($pack->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $pack = sls_pack_main::findOrFail($id);
        $this->assertDraft($pack);
        $data = $this->validatePack($request);
        $this->assertWithinDelivery($data['do_id'], $data['lines'], $pack->id);

        DB::transaction(function () use ($pack, $data, $request) {
            $pack->update(['date' => $data['date'], 'do_id' => $data['do_id'], 'note' => $data['note'] ?? null]);
            $pack->detail()->delete();
            $this->syncLines($pack, $data['lines']);
            AuditLogger::record($request, "Update Packing List {$pack->code}", $pack->code);
        });

        return ApiResponse::item($pack->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $pack = sls_pack_main::findOrFail($id);
        $this->assertDraft($pack);

        DB::transaction(function () use ($pack, $request) {
            $pack->detail()->delete();
            $pack->delete();
            AuditLogger::record($request, "Delete Packing List {$pack->code}", $pack->code);
        });

        return ApiResponse::item(['message' => 'Packing list dihapus.']);
    }

    /** Sealed: the boxes are closed and the document goes with the goods. */
    public function finalize(Request $request, int $id)
    {
        $pack = sls_pack_main::with('detail')->findOrFail($id);
        $this->assertDraft($pack);

        if ($pack->detail->isEmpty()) {
            throw BizException::make('PACK_EMPTY', 'Packing list tanpa kotak tidak dapat difinalkan.');
        }

        $pack->update(['status' => 'FINAL']);
        AuditLogger::record($request, "Finalkan Packing List {$pack->code}", $pack->code);

        return ApiResponse::item($pack->load($this->with));
    }

    /* ---------------- helpers ---------------- */

    private function assertDraft(sls_pack_main $pack): void
    {
        if ($pack->status !== 'DRAFT') {
            throw BizException::make('PACK_LOCKED', 'Packing list yang sudah final tidak dapat diubah.');
        }
    }

    /** How much of each delivery line is already boxed, excluding one list. */
    private function packedByLine(int $doId, ?int $exceptPackId = null): array
    {
        return DB::table('sls_pack_det as d')
            ->join('sls_pack_main as m', 'm.id', '=', 'd.main_id')
            ->where('m.do_id', $doId)
            ->when($exceptPackId, fn ($q) => $q->where('m.id', '<>', $exceptPackId))
            ->groupBy('d.do_detail_id')
            ->selectRaw('d.do_detail_id, SUM(d.qty) as qty')
            ->pluck('qty', 'do_detail_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Boxes must belong to this delivery and must not, together with what other
     * packing lists already hold, exceed what is being delivered.
     */
    private function assertWithinDelivery(int $doId, array $lines, ?int $packId): void
    {
        $do = sls_do_main::with('detail')->findOrFail($doId);
        $already = $this->packedByLine($doId, $packId);

        $wanted = [];
        foreach ($lines as $i => $l) {
            $dd = $do->detail->firstWhere('id', (int) $l['do_detail_id']);
            if (! $dd || (int) $dd->item_id !== (int) $l['item_id']) {
                throw BizException::make('PACK_LINE', 'Kotak #'.($i + 1).': baris DO tidak sesuai.');
            }
            $wanted[$dd->id] = ($wanted[$dd->id] ?? 0) + (int) $l['qty'];
        }

        foreach ($wanted as $lineId => $qty) {
            $dd = $do->detail->firstWhere('id', $lineId);
            $room = (int) $dd->qty - (int) ($already[$lineId] ?? 0);
            if ($qty > $room) {
                throw BizException::make(
                    'PACK_OVER',
                    "Item #{$dd->item_id}: sisa yang belum dikemas {$room} pcs, dikemas {$qty} pcs."
                );
            }
        }
    }

    private function syncLines(sls_pack_main $pack, array $lines): void
    {
        foreach ($lines as $l) {
            $pack->detail()->create([
                'box_no' => $l['box_no'],
                'do_detail_id' => $l['do_detail_id'],
                'item_id' => $l['item_id'],
                'qty' => $l['qty'],
                'net_weight' => $l['net_weight'] ?? 0,
                // Gross defaults to net: a packing list with no packaging weight
                // is still better than one whose gross reads zero.
                'gross_weight' => $l['gross_weight'] ?? ($l['net_weight'] ?? 0),
                'dimension' => $l['dimension'] ?? null,
                'note' => $l['note'] ?? null,
            ]);
        }
    }

    private function validatePack(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'do_id' => ['required', 'integer', 'exists:sls_do_main,id'],
            'note' => ['nullable', 'string', 'max:300'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.box_no' => ['required', 'string', 'max:30'],
            'lines.*.do_detail_id' => ['required', 'integer', 'exists:sls_do_detail,id'],
            'lines.*.item_id' => ['required', 'integer', 'exists:m_item,id'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.net_weight' => ['nullable', 'numeric', 'min:0'],
            'lines.*.gross_weight' => ['nullable', 'numeric', 'min:0'],
            'lines.*.dimension' => ['nullable', 'string', 'max:40'],
            'lines.*.note' => ['nullable', 'string', 'max:150'],
        ]);
    }
}
