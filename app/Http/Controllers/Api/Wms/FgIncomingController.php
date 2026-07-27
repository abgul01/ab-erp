<?php

namespace App\Http\Controllers\Api\Wms;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\tr_inc_fg_det;
use App\Models\tr_inc_fg_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\FgStockService;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Finished-goods stock-in (WMS FG). The floor finishes a piece when its last
 * processing step produces a FULL pallet; scanning that pallet here receives it
 * into the FG warehouse (tr_inc_fg_main/det). On-hand is then Σ received −
 * Σ issued (see FgStockService), which is what the Delivery Order ships against.
 */
class FgIncomingController extends Controller
{
    public function __construct(private FgStockService $fg) {}

    public function index(Request $request)
    {
        $rows = tr_inc_fg_main::query()
            ->withCount('detail')
            ->when($request->query('q'), fn ($q, $s) => $q->where('code', 'like', "%{$s}%"))
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        $main = tr_inc_fg_main::with('detail.item')->findOrFail($id);
        // lot identity = source WIP code; fall back to the pallet code
        $wip = \App\Models\prd_wip::whereIn('id', $main->detail->pluck('wip_id')->filter())->pluck('code', 'id');
        $data = $main->toArray();
        $data['detail'] = collect($data['detail'])->map(function ($d) use ($wip) {
            $d['no_lot'] = $d['wip_id'] ? ($wip[$d['wip_id']] ?? "WIP-{$d['wip_id']}") : $d['pal_pro_code'];

            return $d;
        })->all();

        return ApiResponse::item($data);
    }

    /** On-hand finished goods per item: received − issued, only items with stock. */
    public function stockSummary()
    {
        $stock = $this->fg->stockByItem();
        $items = \App\Models\m_item::whereIn('id', array_keys($stock))->get(['id', 'code', 'part_name'])->keyBy('id');

        $rows = collect($stock)
            ->filter(fn ($q) => $q != 0)
            ->map(fn ($q, $itemId) => [
                'item_id' => (int) $itemId,
                'item_code' => $items[$itemId]->code ?? "#{$itemId}",
                'part_name' => $items[$itemId]->part_name ?? null,
                'on_hand' => (int) $q,
            ])->sortBy('item_code')->values();

        return ApiResponse::collection($rows);
    }

    /**
     * START — open a new receipt (assigns the FG code) so the operator can begin
     * scanning pallets into it. Details are added one scan at a time.
     */
    public function start(Request $request)
    {
        $data = $request->validate(['date' => ['required', 'date']]);
        $main = tr_inc_fg_main::create([
            'code' => $this->shortCode('FGIN', 'FGI'),
            'date' => $data['date'],
            'user_id' => sprintf('U%04d', $request->user()->id),
        ]);
        AuditLogger::record($request, "Start FG Incoming {$main->code}", $main->code);

        return ApiResponse::item(['id' => $main->id, 'code' => $main->code, 'date' => $main->date, 'user_id' => $main->user_id], 201);
    }

    /** SCAN — add one pallet (by its code) to an open receipt; returns the row. */
    public function scan(Request $request, int $id)
    {
        $data = $request->validate(['pallet_code' => ['required', 'string']]);
        $main = tr_inc_fg_main::findOrFail($id);
        $code = trim($data['pallet_code']);

        $p = $this->fg->availablePallets()->firstWhere('code', $code);
        if (! $p) {
            throw BizException::make('FG_PALLET', "Pallet {$code} tidak tersedia (belum FULL di langkah terakhir, atau sudah masuk stok).");
        }

        $det = DB::transaction(function () use ($main, $p, $request) {
            $n = tr_inc_fg_det::where('main_id', $main->id)->count() + 1;
            $det = tr_inc_fg_det::create([
                'code' => sprintf('D%d-%d', $main->id, $n),
                'main_id' => $main->id,
                'pal_pro_code' => $p->code,
                'item_id' => $p->item_id,
                'cut_id' => (int) $p->cut_id,
                'qty' => (int) $p->qty,
                'wip_id' => (int) $p->wip_id,
            ]);
            AuditLogger::record($request, "Scan pallet {$p->code} → FG Incoming {$main->code}", $main->code);

            return $det;
        });

        return ApiResponse::item($this->detailRow($det, $main, $p), 201);
    }

    /** Remove one scanned line from an open receipt. */
    public function removeDet(Request $request, int $detId)
    {
        $det = tr_inc_fg_det::findOrFail($detId);
        // block if the received FG has since been shipped
        if ($this->fg->stock((int) $det->item_id) - (int) $det->qty < 0) {
            throw BizException::make('FG_ISSUED', 'FG ini sudah dikirim, tidak dapat dihapus.');
        }
        $det->delete();

        return ApiResponse::item(['message' => 'Baris dihapus.']);
    }

    /** FINISH — a receipt must have at least one scanned pallet. */
    public function finish(Request $request, int $id)
    {
        $main = tr_inc_fg_main::with('detail')->findOrFail($id);
        if ($main->detail->isEmpty()) {
            throw BizException::make('FG_EMPTY', 'Belum ada pallet yang discan.');
        }
        AuditLogger::record($request, "Finish FG Incoming {$main->code}: {$main->detail->count()} pallet", $main->code);

        return $this->show($main->id);
    }

    /** Enriched detail row for the transaction-details table. */
    private function detailRow(tr_inc_fg_det $det, tr_inc_fg_main $main, object $p): array
    {
        return [
            'id' => $det->id,
            'code' => $main->code,
            'customer' => $p->customer,
            'date' => $main->date,
            'user_id' => $main->user_id,
            'part_fg' => $p->part_name,
            'item_code' => $p->item_code,
            'wip' => $p->wip_code,
            'size' => $p->size,
            'code_pallet' => $p->code,
            'qty_pallet' => (int) $p->qty,
            'qty_fg' => (int) $p->qty,
            'source' => $p->source,
        ];
    }

    /** Finished FULL pallets waiting to be received into the FG warehouse. */
    public function available(Request $request)
    {
        $itemId = $request->query('item_id') ? (int) $request->query('item_id') : null;

        return ApiResponse::collection($this->fg->availablePallets($itemId));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'pallets' => ['required', 'array', 'min:1'],
            'pallets.*' => ['required', 'string'],
        ]);

        // resolve the scanned pallet codes against what is genuinely available
        $avail = $this->fg->availablePallets()->keyBy('code');
        $picked = [];
        foreach ($data['pallets'] as $code) {
            $p = $avail->get($code);
            if (! $p) {
                throw BizException::make('FG_PALLET', "Pallet {$code} tidak tersedia untuk diterima (belum FULL di langkah terakhir, atau sudah masuk stok).");
            }
            $picked[$code] = $p;   // de-dupe scans of the same pallet
        }

        $main = DB::transaction(function () use ($data, $picked, $request) {
            $main = tr_inc_fg_main::create([
                'code' => $this->shortCode('FGIN', 'FGI'),
                'date' => $data['date'],
                'user_id' => sprintf('U%04d', $request->user()->id),
            ]);
            foreach (array_values($picked) as $i => $p) {
                tr_inc_fg_det::create([
                    'code' => sprintf('D%d-%d', $main->id, $i + 1),
                    'main_id' => $main->id,
                    'pal_pro_code' => $p->code,
                    'item_id' => $p->item_id,
                    'cut_id' => (int) $p->cut_id,
                    'qty' => (int) $p->qty,
                    'wip_id' => (int) $p->wip_id,
                ]);
            }
            AuditLogger::record($request, "FG Incoming {$main->code}: " . count($picked) . ' pallet', $main->code);

            return $main;
        });

        return ApiResponse::item($main->load('detail.item'), 201);
    }

    public function destroy(Request $request, int $id)
    {
        $main = tr_inc_fg_main::with('detail')->findOrFail($id);

        // block if reversing would drive any item's on-hand below zero (already shipped)
        foreach ($main->detail->groupBy('item_id') as $itemId => $rows) {
            if ($this->fg->stock((int) $itemId) - (int) $rows->sum('qty') < 0) {
                throw BizException::make('FG_ISSUED', 'Sebagian FG dari dokumen ini sudah dikirim, tidak dapat dibatalkan.');
            }
        }

        DB::transaction(function () use ($main, $request) {
            tr_inc_fg_det::where('main_id', $main->id)->delete();
            $main->delete();
            AuditLogger::record($request, "Delete FG Incoming {$main->code}", $main->code);
        });

        return ApiResponse::item(['message' => 'Penerimaan FG dibatalkan.']);
    }

    /** Monthly-reset document number, kept inside the 20-char code column. */
    private function shortCode(string $type, string $prefix): string
    {
        return (new NumberingService)->next($type, $prefix);
    }
}
