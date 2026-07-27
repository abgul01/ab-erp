<?php

namespace App\Http\Controllers\Api\Wms;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\sls_do_main;
use App\Models\sls_so_main;
use App\Models\tr_out_fg_det;
use App\Models\tr_out_fg_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\FgStockService;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FG Outgoing — scan-based warehouse shipment (reference app style). Start picks
 * a DRAFT Delivery Order and opens an outgoing transaction (assigns the FG-out
 * code); each scanned pallet/lot code issues that lot against the DO. Finish
 * bumps the SO's delivered qty and marks the DO SHIPPED; Cancel reverses.
 *
 * The physical stock moves as pallets are scanned (tr_out_fg_det per lot), so an
 * open transaction reserves the lots; cancelling deletes the rows and restores
 * stock. The Delivery Order stays the sales document.
 */
class FgOutgoingController extends Controller
{
    public function __construct(private FgStockService $fg) {}

    public function index(Request $request)
    {
        $rows = tr_out_fg_main::query()->withCount('detail')
            ->when($request->query('q'), fn ($q, $s) => $q->where('code', 'like', "%{$s}%")->orWhere('code_do', 'like', "%{$s}%"))
            ->orderByDesc('id')->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        $main = tr_out_fg_main::with('detail.item')->findOrFail($id);

        return ApiResponse::item($main);
    }

    /** DRAFT delivery orders available to ship (with per-item outstanding). */
    public function openDos()
    {
        $rows = sls_do_main::with(['so.cus', 'detail.item'])->where('status', 'DRAFT')->orderByDesc('id')->get();

        return ApiResponse::collection($rows->map(fn ($do) => [
            'id' => $do->id, 'code' => $do->code, 'customer' => $do->so?->cus?->company_n,
            'lines' => $do->detail->map(fn ($d) => [
                'item_id' => $d->item_id, 'item_code' => $d->item?->code, 'qty' => (int) $d->qty,
            ])->values(),
        ]));
    }

    /**
     * Cek SJ — resolve a scanned Surat Jalan (the DO code) to its customer and
     * lines, so the operator can confirm before starting the outgoing.
     */
    public function checkSj(Request $request)
    {
        $data = $request->validate(['sj' => ['required', 'string']]);
        $do = sls_do_main::with(['so.cus', 'detail.item'])->where('code', trim($data['sj']))->first();
        if (! $do) {
            throw BizException::make('FGO_SJ', 'Surat Jalan (DO) tidak ditemukan.');
        }
        if ($do->status !== 'DRAFT') {
            throw BizException::make('FGO_SJ_STATE', "Surat Jalan ini sudah berstatus {$do->status}.");
        }
        if (tr_out_fg_main::where('code_do', $do->code)->exists()) {
            throw BizException::make('FGO_OPEN', 'Surat Jalan ini sudah punya transaksi outgoing berjalan.');
        }

        return ApiResponse::item([
            'do_id' => $do->id, 'do_code' => $do->code, 'customer' => $do->so?->cus?->company_n,
            'lines' => $do->detail->map(fn ($d) => [
                'item_id' => $d->item_id, 'item_code' => $d->item?->code, 'part_name' => $d->item?->part_name,
                'qty' => (int) $d->qty,
            ])->values(),
        ]);
    }

    /** START — open an outgoing transaction against a DRAFT DO. */
    public function start(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'do_id' => ['required', 'integer', 'exists:sls_do_main,id'],
        ]);
        $do = sls_do_main::with('so.cus')->findOrFail($data['do_id']);
        if ($do->status !== 'DRAFT') {
            throw BizException::make('FGO_DO', 'Hanya DO berstatus DRAFT yang dapat dikirim.');
        }
        if (tr_out_fg_main::where('code_do', $do->code)->exists()) {
            throw BizException::make('FGO_OPEN', 'DO ini sudah punya transaksi outgoing berjalan.');
        }

        $main = tr_out_fg_main::create([
            'code' => (new NumberingService)->next('FGOUT', 'FGO'),
            'code_do' => $do->code,
            'date' => $data['date'],
            'user_id' => sprintf('U%04d', $request->user()->id),
        ]);
        AuditLogger::record($request, "Start FG Outgoing {$main->code} (DO {$do->code})", $main->code);

        return ApiResponse::item([
            'id' => $main->id, 'code' => $main->code, 'date' => $main->date, 'user_id' => $main->user_id,
            'do_id' => $do->id, 'do_code' => $do->code, 'customer' => $do->so?->cus?->company_n,
        ], 201);
    }

    /** SCAN — issue one FG lot (by its pallet or lot code) against the DO. */
    public function scan(Request $request, int $id)
    {
        $data = $request->validate(['pallet_code' => ['required', 'string']]);
        $main = tr_out_fg_main::findOrFail($id);
        $do = sls_do_main::with(['so.cus', 'detail'])->where('code', $main->code_do)->firstOrFail();
        $code = trim($data['pallet_code']);

        // resolve the scanned code to an on-hand lot (by pallet code or lot code)
        $lot = $this->fg->availableLots()->first(fn ($l) => $l->pal_pro_code === $code || $l->lot_code === $code);
        if (! $lot) {
            throw BizException::make('FGO_LOT', "Pallet/lot {$code} tidak ada di stok FG (atau sudah keluar).");
        }
        // the lot's item must be on this DO, with qty still outstanding
        $outstanding = $this->outstanding($do, (int) $lot->item_id, $main->id);
        if ($outstanding <= 0) {
            throw BizException::make('FGO_ITEM', 'Item pallet ini tidak ada / sudah terpenuhi pada DO ini.');
        }
        $qtyFg = (int) $lot->remaining;      // pieces available in the scanned pallet/lot
        $qtyOut = min($qtyFg, $outstanding); // pieces actually shipped on this DO

        $det = DB::transaction(function () use ($main, $lot, $qtyOut) {
            $n = tr_out_fg_det::where('main_id', $main->id)->count() + 1;

            return tr_out_fg_det::create([
                'main_id' => $main->id,
                'item_id' => $lot->item_id,
                'fg_code' => $lot->lot_code,          // the FG lot this qty came from
                'code' => sprintf('FGO%d-%d', $main->id, $n),
                'qty' => $qtyOut,
            ]);
        });

        $item = DB::table('m_item')->where('id', $lot->item_id)->first(['code', 'part_name', 'length']);

        return ApiResponse::item([
            'id' => $det->id, 'code' => $main->code, 'customer' => $do->so?->cus?->company_n,
            'date' => $main->date, 'user_id' => $main->user_id,
            'code_fg' => $item->code ?? null, 'part_fg' => $item->part_name ?? null,
            'wip' => $lot->wip_id ? "WIP-{$lot->wip_id}" : $lot->pal_pro_code,
            'pallet_code' => $lot->pal_pro_code, 'lot' => $lot->lot_code,
            'size' => $item->length ?? null, 'qty_fg' => $qtyFg, 'qty_out' => $qtyOut,
        ], 201);
    }

    public function removeDet(Request $request, int $detId)
    {
        tr_out_fg_det::findOrFail($detId)->delete();

        return ApiResponse::item(['message' => 'Baris dihapus.']);
    }

    /** FINISH — commit: bump the SO delivered qty and ship the DO. */
    public function finish(Request $request, int $id)
    {
        $main = tr_out_fg_main::with('detail')->findOrFail($id);
        if ($main->detail->isEmpty()) {
            throw BizException::make('FGO_EMPTY', 'Belum ada pallet yang discan.');
        }
        $do = sls_do_main::where('code', $main->code_do)->firstOrFail();

        DB::transaction(function () use ($main, $do, $request) {
            $so = sls_so_main::with('detail')->lockForUpdate()->findOrFail($do->so_id);

            // distribute the scanned qty per item across that item's DO lines
            $perItem = $main->detail->groupBy('item_id')->map(fn ($g) => (int) $g->sum('qty'));
            foreach ($do->detail()->get() as $line) {
                $take = min((int) $line->qty, $perItem[$line->item_id] ?? 0);
                if ($take > 0) {
                    $sd = $so->detail->firstWhere('id', $line->so_detail_id);
                    $sd?->increment('qty_delivered', $take);
                    $perItem[$line->item_id] -= $take;
                }
            }

            $do->update(['status' => 'SHIPPED']);
            $so->refresh();
            if ($so->detail->every(fn ($d) => (int) $d->qty_delivered >= (int) $d->qty)) {
                $so->update(['status' => 'CLOSED']);
            }
            AuditLogger::record($request, "Finish FG Outgoing {$main->code} → DO {$do->code} SHIPPED", $main->code);
        });

        return $this->show($main->id);
    }

    /** CANCEL — drop the transaction and restore stock (out rows deleted). */
    public function destroy(Request $request, int $id)
    {
        $main = tr_out_fg_main::findOrFail($id);
        $do = sls_do_main::where('code', $main->code_do)->first();
        if ($do && $do->status !== 'DRAFT') {
            throw BizException::make('FGO_SHIPPED', 'Transaksi sudah selesai (DO terkirim), tidak dapat dibatalkan di sini.');
        }
        DB::transaction(function () use ($main, $request) {
            tr_out_fg_det::where('main_id', $main->id)->delete();
            $main->delete();
            AuditLogger::record($request, "Cancel FG Outgoing {$main->code}", $main->code);
        });

        return ApiResponse::item(['message' => 'Transaksi outgoing dibatalkan.']);
    }

    /** DO line outstanding for an item, minus what this open txn already scanned. */
    private function outstanding(sls_do_main $do, int $itemId, int $outId): int
    {
        $ordered = (int) $do->detail->where('item_id', $itemId)->sum('qty');
        $delivered = (int) $do->detail->where('item_id', $itemId)->sum(fn ($d) => (int) DB::table('sls_so_detail')->where('id', $d->so_detail_id)->value('qty_delivered'));
        $scanned = (int) tr_out_fg_det::where('main_id', $outId)->where('item_id', $itemId)->sum('qty');

        return $ordered - $delivered - $scanned;
    }
}
