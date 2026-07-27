<?php

namespace App\Http\Controllers\Api\Sales;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\sls_do_detail;
use App\Models\sls_do_main;
use App\Models\sls_so_detail;
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
 * Delivery Order (Fase 5 fulfilment). Ships an APPROVED Sales Order against
 * finished-goods stock. Lifecycle: DRAFT → SHIPPED → RECEIVED, with INVOICED
 * set later by the Sales Invoice.
 *
 * The stock move happens at SHIP: it writes tr_out_fg (FG issue), bumps
 * sls_so_detail.qty_delivered, and closes the SO once every line is fully
 * delivered. A DRAFT reserves nothing, so it can be freely edited or dropped.
 */
class DoController extends Controller
{
    private array $with = ['so.cus', 'user', 'detail.item'];

    public function __construct(private FgStockService $fg) {}

    public function index(Request $request)
    {
        $rows = sls_do_main::query()
            ->with(['so.cus'])->withCount('detail')
            ->when($request->query('q'), fn ($q, $s) => $q->where('code', 'like', "%{$s}%")
                ->orWhereHas('so', fn ($w) => $w->where('code', 'like', "%{$s}%")))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        return ApiResponse::item(sls_do_main::with($this->with)->findOrFail($id));
    }

    /** Approved SOs that still have undelivered lines — for the DO picker. */
    public function openSos(Request $request)
    {
        $rows = sls_so_main::with('cus')->where('status', 'APPROVED')
            ->whereHas('detail', fn ($q) => $q->whereColumn('qty_delivered', '<', 'qty'))
            ->orderByDesc('id')->get(['id', 'code', 'date', 'cus_id']);

        return ApiResponse::collection($rows->map(fn ($s) => [
            'id' => $s->id, 'code' => $s->code, 'date' => $s->date,
            'customer' => $s->cus?->company_n,
        ]));
    }

    /**
     * Deliverable lines of an SO: outstanding qty (ordered − delivered) capped
     * against on-hand FG stock, so the operator only ships what physically exists.
     */
    public function soLines(int $soId)
    {
        $so = sls_so_main::with('detail.item')->findOrFail($soId);
        $stock = $this->fg->stockByItem();

        $lines = $so->detail
            ->map(function ($d) use ($stock) {
                $outstanding = max(0, (int) $d->qty - (int) $d->qty_delivered);

                return [
                    'so_detail_id' => $d->id,
                    'item_id' => $d->item_id,
                    'item_code' => $d->item?->code,
                    'part_name' => $d->item?->part_name,
                    'qty_order' => (int) $d->qty,
                    'qty_delivered' => (int) $d->qty_delivered,
                    'outstanding' => $outstanding,
                    'fg_stock' => (int) ($stock[$d->item_id] ?? 0),
                ];
            })
            ->filter(fn ($l) => $l['outstanding'] > 0)
            ->values();

        return ApiResponse::collection($lines);
    }

    public function store(Request $request)
    {
        $data = $this->validateDo($request);
        $so = sls_so_main::with('detail')->findOrFail($data['so_id']);
        if ($so->status !== 'APPROVED') {
            throw BizException::make('DO_SO_STATE', 'Delivery Order hanya untuk SO berstatus APPROVED.');
        }
        $this->assertLines($so, $data['lines']);

        $do = DB::transaction(function () use ($so, $data, $request) {
            $do = sls_do_main::create([
                'code' => (new NumberingService)->next('DO', 'DO'),
                'date' => $data['date'],
                'so_id' => $so->id,
                'user_id' => $request->user()->id,
                'status' => 'DRAFT',
            ]);
            $this->syncLines($do, $data['lines']);
            AuditLogger::record($request, "Create DO {$do->code} (SO {$so->code})", $do->code);

            return $do;
        });

        return ApiResponse::item($do->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $do = sls_do_main::findOrFail($id);
        $this->assertDraft($do);
        $data = $this->validateDo($request);
        $so = sls_so_main::with('detail')->findOrFail($do->so_id);
        $this->assertLines($so, $data['lines']);

        DB::transaction(function () use ($do, $data, $request) {
            $do->update(['date' => $data['date']]);
            $do->detail()->delete();
            $this->syncLines($do, $data['lines']);
            AuditLogger::record($request, "Update DO {$do->code}", $do->code);
        });

        return ApiResponse::item($do->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $do = sls_do_main::findOrFail($id);
        $this->assertDraft($do);
        DB::transaction(function () use ($do, $request) {
            $do->detail()->delete();
            $do->delete();
            AuditLogger::record($request, "Delete DO {$do->code}", $do->code);
        });

        return ApiResponse::item(['message' => 'Delivery Order dihapus.']);
    }

    /**
     * Ship context: each DO line with the FG lots available to draw from, so the
     * operator can pick which lot(s) to ship (or let the server auto-FIFO).
     */
    public function shipInfo(int $id)
    {
        $do = sls_do_main::with('detail.item')->findOrFail($id);

        return ApiResponse::item([
            'id' => $do->id, 'code' => $do->code, 'status' => $do->status,
            'lines' => $do->detail->map(fn ($d) => [
                'do_detail_id' => $d->id, 'item_id' => $d->item_id,
                'item_code' => $d->item?->code, 'part_name' => $d->item?->part_name,
                'qty' => (int) $d->qty,
                'lots' => $this->fg->availableLots($d->item_id)->map(fn ($l) => [
                    'lot_code' => $l->lot_code, 'no_lot' => $l->wip_id ? "WIP-{$l->wip_id}" : $l->pal_pro_code,
                    'pallet' => $l->pal_pro_code, 'wip_id' => $l->wip_id,
                    'receipt' => $l->receipt_code, 'date' => $l->date, 'remaining' => $l->remaining,
                ])->values(),
            ])->values(),
        ]);
    }

    /**
     * SHIP — the real stock move, drawing each line from specific FG lots.
     * The operator sends an allocation per line (lot_code → qty); with `auto`
     * (or a line left unallocated) the server picks lots FIFO. Issues tr_out_fg
     * per lot, bumps qty_delivered, and closes the SO when fully delivered.
     */
    public function ship(Request $request, int $id)
    {
        $data = $request->validate([
            'auto' => ['nullable', 'boolean'],
            'allocations' => ['nullable', 'array'],
            'allocations.*' => ['array'],
            'allocations.*.*.lot_code' => ['required', 'string'],
            'allocations.*.*.qty' => ['required', 'integer', 'min:1'],
        ]);
        $do = sls_do_main::with('detail')->findOrFail($id);
        if ($do->status !== 'DRAFT') {
            throw BizException::make('DO_STATE', 'Hanya DO DRAFT yang dapat dikirim.');
        }
        if ($do->detail->isEmpty()) {
            throw BizException::make('DO_EMPTY', 'DO tanpa baris tidak dapat dikirim.');
        }
        $allocations = $data['allocations'] ?? [];

        DB::transaction(function () use ($do, $request, $allocations) {
            $so = sls_so_main::with('detail')->lockForUpdate()->findOrFail($do->so_id);

            $out = tr_out_fg_main::create([
                'code' => $this->outCode(),
                'code_do' => $do->code,
                'date' => now()->toDateString(),
                'user_id' => sprintf('U%04d', $request->user()->id),
            ]);

            $seq = 0;
            foreach ($do->detail as $line) {
                // resolve which lots feed this line: explicit picks or FIFO
                $picks = $this->resolvePicks($line, $allocations[$line->id] ?? null);
                foreach ($picks as $p) {
                    tr_out_fg_det::create([
                        'main_id' => $out->id,
                        'item_id' => $line->item_id,
                        'fg_code' => $p['lot_code'],   // the FG lot this qty came from
                        'code' => sprintf('DO%d-%d', $out->id, ++$seq),
                        'qty' => $p['qty'],
                    ]);
                }
                $sd = $so->detail->firstWhere('id', $line->so_detail_id);
                if ($sd) {
                    $sd->increment('qty_delivered', (int) $line->qty);
                }
            }

            $do->update(['status' => 'SHIPPED']);

            $so->refresh();
            if ($so->detail->every(fn ($d) => (int) $d->qty_delivered >= (int) $d->qty)) {
                $so->update(['status' => 'CLOSED']);
            }

            AuditLogger::record($request, "Ship DO {$do->code} → FG out {$out->code}", $do->code);
        });

        return ApiResponse::item($do->load($this->with));
    }

    /**
     * Which lots (and how much of each) feed a DO line. Validates explicit picks
     * against live lot balances; falls back to consuming oldest lots first.
     *
     * @return array<int, array{lot_code: string, qty: int}>
     */
    private function resolvePicks(sls_do_detail $line, ?array $alloc): array
    {
        $need = (int) $line->qty;
        $lots = $this->fg->availableLots($line->item_id)->keyBy('lot_code');

        if ($alloc) {
            $picks = [];
            $sum = 0;
            foreach ($alloc as $a) {
                $lot = $lots->get($a['lot_code']);
                if (! $lot) {
                    throw BizException::make('DO_LOT', "Lot {$a['lot_code']} tidak tersedia untuk item ini.");
                }
                if ((int) $a['qty'] > $lot->remaining) {
                    throw BizException::make('DO_LOT_QTY', "Lot {$a['lot_code']} sisa {$lot->remaining}, diminta {$a['qty']}.");
                }
                $picks[] = ['lot_code' => $a['lot_code'], 'qty' => (int) $a['qty']];
                $sum += (int) $a['qty'];
            }
            if ($sum !== $need) {
                throw BizException::make('DO_LOT_SUM', "Total lot ({$sum}) tidak sama dengan qty baris ({$need}).");
            }

            return $picks;
        }

        // auto FIFO: consume oldest lots until the line qty is met
        $picks = [];
        foreach ($lots as $lot) {
            if ($need <= 0) {
                break;
            }
            $take = min($need, $lot->remaining);
            $picks[] = ['lot_code' => $lot->lot_code, 'qty' => $take];
            $need -= $take;
        }
        if ($need > 0) {
            throw BizException::make('DO_STOCK', "Stok FG item #{$line->item_id} tidak cukup untuk dikirim.");
        }

        return $picks;
    }

    /** Customer confirms receipt: SHIPPED → RECEIVED (no stock effect). */
    public function receive(Request $request, int $id)
    {
        $do = sls_do_main::findOrFail($id);
        if ($do->status !== 'SHIPPED') {
            throw BizException::make('DO_STATE', 'DO harus berstatus SHIPPED untuk ditandai diterima.');
        }
        $do->update(['status' => 'RECEIVED']);
        AuditLogger::record($request, "Receive DO {$do->code}", $do->code);

        return ApiResponse::item($do->load($this->with));
    }

    /* ---------------- helpers ---------------- */

    private function assertDraft(sls_do_main $do): void
    {
        if ($do->status !== 'DRAFT') {
            throw BizException::make('DO_LOCKED', 'DO yang sudah dikirim tidak dapat diubah.');
        }
    }

    /** Each line: valid SO line, qty within outstanding, qty within FG stock. */
    private function assertLines(sls_so_main $so, array $lines): void
    {
        $stock = $this->fg->stockByItem();
        $byItem = [];
        foreach ($lines as $i => $l) {
            $sd = $so->detail->firstWhere('id', (int) $l['so_detail_id']);
            if (! $sd || (int) $sd->item_id !== (int) $l['item_id']) {
                throw BizException::make('DO_LINE', 'Baris #' . ($i + 1) . ': baris SO tidak valid.');
            }
            $outstanding = (int) $sd->qty - (int) $sd->qty_delivered;
            if ((int) $l['qty'] > $outstanding) {
                throw BizException::make('DO_OVER', "Baris #" . ($i + 1) . ": qty melebihi sisa order ({$outstanding}).");
            }
            $byItem[$l['item_id']] = ($byItem[$l['item_id']] ?? 0) + (int) $l['qty'];
        }
        foreach ($byItem as $itemId => $need) {
            if ($need > (int) ($stock[$itemId] ?? 0)) {
                throw BizException::make('DO_STOCK', "Stok FG untuk item #{$itemId} tidak cukup (tersedia " . (int) ($stock[$itemId] ?? 0) . ", diminta {$need}).");
            }
        }
    }

    private function syncLines(sls_do_main $do, array $lines): void
    {
        foreach ($lines as $l) {
            sls_do_detail::create([
                'main_id' => $do->id,
                'so_detail_id' => $l['so_detail_id'],
                'item_id' => $l['item_id'],
                'qty' => $l['qty'],
                'fg_code' => null,
            ]);
        }
    }

    private function validateDo(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'so_id' => ['required', 'integer', 'exists:sls_so_main,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.so_detail_id' => ['required', 'integer', 'exists:sls_so_detail,id'],
            'lines.*.item_id' => ['required', 'integer', 'exists:m_item,id'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
        ]);
    }

    /** tr_out_fg_main.code is varchar(20) → keep the numbered code short. */
    private function outCode(): string
    {
        return (new NumberingService)->next('FGOUT', 'FGO');
    }
}
