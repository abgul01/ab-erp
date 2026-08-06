<?php

namespace App\Support;

use App\Models\m_item;
use App\Models\prc_pr_detail;
use App\Models\prc_pr_main;
use App\Models\prd_mrp_detail;
use App\Models\prd_mrp_main;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Material Requirements Planning engine.
 * Extracted from MrpController so it can run synchronously or via a background job.
 *
 * LLD §4.7
 */
class MrpService
{
    public function __construct(private FgStockService $fg) {}

    /**
     * Run MRP for a set of periods and persist the results.
     *
     * @param  array<int, string>  $periods  e.g. ['202607', '202608']
     */
    public function run(array $periods, ?int $userId = null): prd_mrp_main
    {
        $rows = $this->explode($periods);

        $main = DB::transaction(function () use ($rows, $userId) {
            $main = prd_mrp_main::create([
                'run_date' => now(),
                // The column is NOT NULL, so a run started by a queue worker
                // has to carry whoever asked for it — without this the job
                // failed the moment it tried to write its result.
                'user_id' => $userId ?? auth()->id() ?? 1,
                'status' => 'DONE',
                'created_at' => now(),
            ]);
            foreach ($rows as $r) {
                prd_mrp_detail::create(['main_id' => $main->id] + $r);
            }

            return $main;
        });

        return $main;
    }

    /**
     * Fill a run the caller already created.
     *
     * The controller inserts a PROCESSING row and hands its id to the browser
     * to poll. If the worker created its own row instead and deleted that one,
     * the page would be polling an id that no longer exists — so the job writes
     * into the row it was given.
     */
    public function runInto(int $mainId, array $periods): prd_mrp_main
    {
        $rows = $this->explode($periods);

        return DB::transaction(function () use ($mainId, $rows) {
            $main = prd_mrp_main::findOrFail($mainId);

            prd_mrp_detail::where('main_id', $main->id)->delete();
            foreach ($rows as $r) {
                prd_mrp_detail::create(['main_id' => $main->id] + $r);
            }

            $main->update(['status' => 'DONE']);

            return $main->fresh();
        });
    }

    /**
     * Generate Purchase Requisition from MRP results.
     *
     * Creates a single PR (pr_type=MRP) for all RM items with net_req > 0
     * and suggestion='PR'. Groups by item across periods, totals net_req.
     * The PR is created in DRAFT status ready for submission and approval.
     *
     * @return array{pr_id: int, code: string, items: int}
     */
    public function generatePr(int $mrpMainId, ?int $userId = null): array
    {
        $details = prd_mrp_detail::where('main_id', $mrpMainId)
            ->where('suggestion', 'PR')
            ->where('net_req', '>', 0)
            ->get();

        if ($details->isEmpty()) {
            throw new \RuntimeException(
                'Tidak ada material yang perlu dibeli dari MRP run ini — kebutuhan sudah tertutup stok, PO berjalan, atau PR yang sudah dibuat.'
            );
        }

        /*
         * One line per material, but the earliest period decides when it is
         * needed: a material required in July and again in August has to arrive
         * for July, so the need date follows the first shortage, not the last.
         */
        $grouped = $details->groupBy('item_id')->map(fn ($rows) => [
            'item_id' => (int) $rows->first()->item_id,
            'qty' => (int) $rows->sum('net_req'),
            'net_req_kg' => (float) $rows->sum('net_req_kg'),
            'first_period' => $rows->min('period'),
        ])->values();

        $items = m_item::whereIn('id', $grouped->pluck('item_id'))->get()->keyBy('id');
        $today = now()->startOfDay();

        $main = DB::transaction(function () use ($grouped, $mrpMainId, $userId, $today) {
            $main = prc_pr_main::create([
                'code' => app(NumberingService::class)->next('PR', 'MRP'),
                'date' => now()->toDateString(),
                'pr_type' => 'MRP',
                'user_id' => $userId ?? auth()->id() ?? 1,
                'status' => 'DRAFT',
            ]);

            foreach ($grouped as $g) {
                // Terms come from the preferred supplier where one exists, so
                // the requisition can already say who to buy from and roughly
                // what it costs instead of leaving the buyer to work it out.
                $terms = $this->supplierTerms($g['item_id']);
                $leadDays = $terms['lead'];

                /*
                 * Order by the date the buyer has to act, not the date the
                 * material is wanted. Imported steel on 45-day lead time
                 * ordered on the first of the month it is needed arrives six
                 * weeks late — the shortage is already there by then.
                 */
                $neededBy = Carbon::parse($this->periodStart($g['first_period']));
                $orderBy = $neededBy->copy()->subDays($leadDays);

                // A lead time longer than the notice we have is worth saying out
                // loud rather than silently back-dating the requisition.
                $isLate = $orderBy->lt($today);

                prc_pr_detail::create([
                    'main_id' => $main->id,
                    'item_id' => $g['item_id'],
                    'ven_id' => $terms['ven_id'],
                    'est_price' => $terms['price'],
                    // The quantity is what MRP already netted and rounded to a
                    // lot; adding safety stock again here would order it twice.
                    'qty' => $g['qty'],
                    'uom_id' => DB::table('m_uom')->where('code', 'PCS')->value('id'),
                    'need_date' => ($isLate ? $today : $orderBy)->toDateString(),
                    // note is varchar(150); the derivation has to fit, so it is
                    // kept terse and truncated rather than silently rejected.
                    'note' => mb_substr(sprintf(
                        'MRP#%d %s: %s pcs (%s kg), LT %dh, butuh %s%s%s',
                        $mrpMainId,
                        $g['first_period'],
                        number_format($g['qty']),
                        number_format($g['net_req_kg'], 2),
                        $leadDays,
                        $neededBy->toDateString(),
                        $terms['source'] === 'SUPPLIER' ? ', supplier prioritas' : ', supplier belum ada',
                        $isLate ? ' — TERLAMBAT' : ''
                    ), 0, 150),
                ]);
            }

            return $main;
        });

        return [
            'pr_id' => $main->id,
            'code' => $main->code,
            'items' => $grouped->count(),
            'total_qty' => (int) $grouped->sum('qty'),
            'total_kg' => round($grouped->sum('net_req_kg'), 2),
        ];
    }

    /**
     * Buying terms for a material: the preferred supplier's, or the item's.
     *
     * MOQ, order lot and lead time belong to a supplier — one mill sells in
     * bundles of 25 on 45-day terms, another sells singles in a fortnight.
     * The item-level columns remain the fallback for materials that have no
     * supplier agreed yet, so planning still works while the master is filled in.
     *
     * @return array{ven_id:?int, moq:int, lot:int, lead:int, price:float, source:string}
     */
    public function supplierTerms(int $itemId): array
    {
        $today = now()->toDateString();

        $s = DB::table('m_supplier_item')
            ->where('item_id', $itemId)
            ->where('active', 1)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today))
            // Cheapest among equals: priority first, then price.
            ->orderBy('priority')->orderBy('price')
            ->first();

        if ($s) {
            return [
                'ven_id' => (int) $s->ven_id,
                'moq' => max(0, (int) $s->moq),
                'lot' => max(0, (int) $s->order_lot),
                'lead' => max(0, (int) $s->lead_time_days),
                'price' => (float) $s->price,
                'source' => 'SUPPLIER',
            ];
        }

        $item = DB::table('m_item')->where('id', $itemId)->first(['moq', 'order_lot', 'lead_time_days']);

        return [
            'ven_id' => null,
            'moq' => max(0, (int) ($item->moq ?? 0)),
            'lot' => max(0, (int) ($item->order_lot ?? 0)),
            'lead' => max(0, (int) ($item->lead_time_days ?? 0)),
            'price' => 0.0,
            'source' => 'ITEM',
        ];
    }

    /** Material for a period has to be on hand before that period starts. */
    private function periodStart(?string $period): string
    {
        if (! $period || ! preg_match('/^\d{6}$/', $period)) {
            return now()->addWeek()->toDateString();
        }

        return substr($period, 0, 4).'-'.substr($period, 4, 2).'-01';
    }

    /**
     * Time-phased explosion across sorted periods.
     *
     * @param  array<int, string>  $periods
     * @return array<int, array<string, mixed>>
     */
    public function explode(array $periods): array
    {
        $rmOnhand = [];
        $rmMeta = [];
        $fgOnhand = [];
        $fgFirst = [];      // where each product's supply came from, first period only
        $fgSeen = [];
        $rmSeen = [];
        $out = [];

        foreach ($periods as $period) {
            $demand = $this->grossDemand($period);
            $rmGross = [];

            foreach ($demand as $fgId => $d) {
                $fgId = (int) $fgId;
                $planQty = (int) $d['qty'];

                if ($planQty <= 0) {
                    continue;
                }

                /*
                 * Supply is counted once and then carried forward: goods already
                 * being made for July are not available again for August.
                 *
                 * What each row reports is the supply that was actually applied
                 * to THAT period — the opening balance carried in, plus new
                 * supply only in the period where it first appears. Reporting
                 * the original snapshot on every row (as this used to) produced
                 * lines that could not be checked: gross 1110 against 1820 of
                 * open work orders, yet a net requirement of 340.
                 */
                if (! isset($fgOnhand[$fgId])) {
                    // Stock can go negative if more was shipped than received;
                    // that is a stock error to fix, not spare supply to plan on.
                    $stock = max(0, $this->fgStock($fgId));
                    $openWo = $this->openWo($fgId);

                    $fgOnhand[$fgId] = $stock + $openWo;
                    $fgFirst[$fgId] = ['onhand' => $stock, 'open_wo' => $openWo];
                }

                $avail = $fgOnhand[$fgId];
                $net = max(0, $planQty - $avail);
                $fgOnhand[$fgId] = max(0, $avail - $planQty);

                // First period shows where the supply came from; later periods
                // show only what survived the earlier demand.
                $first = $fgFirst[$fgId] ?? ['onhand' => 0, 'open_wo' => 0];
                $isFirst = ! isset($fgSeen[$fgId]);
                $fgSeen[$fgId] = true;

                $out[] = [
                    'item_id' => $fgId, 'period' => $period,
                    'gross_req' => $planQty,
                    'demand_src' => $d['src'],
                    'demand_mpp' => $d['mpp'],
                    'demand_fc' => $d['fc'],
                    'demand_so' => $d['so'],
                    'onhand' => $isFirst ? $first['onhand'] : $avail,
                    'open_po' => 0,
                    'open_wo' => $isFirst ? $first['open_wo'] : 0,
                    'net_req' => $net, 'net_req_kg' => null,
                    'suggestion' => $net > 0 ? 'WO' : null,
                ];

                if ($net > 0) {
                    foreach ($this->bomRm((int) $fgId) as $rm) {
                        $bar = $rmMeta[$rm['mat_id']]['bar'] ??= max(1.0, (float) $rm['bar_length']);
                        $bars = (int) ceil($net * $rm['length_use'] / $bar);
                        $rmGross[$rm['mat_id']] = ($rmGross[$rm['mat_id']] ?? 0) + $bars;
                        $rmMeta[$rm['mat_id']]['weight'] ??= (float) $rm['weight'];
                    }
                }
            }

            foreach ($rmGross as $rmId => $gross) {
                $rmId = (int) $rmId;

                /*
                 * Netting, and the reason this engine exists: what has to be
                 * bought is the requirement less everything already covering it
                 * — stock on the rack, purchase orders in transit, and
                 * requisitions already raised.
                 *
                 * The figures are read once per material and then carried
                 * forward across periods, so material bought for July is not
                 * counted again as available for August.
                 */
                if (! isset($rmOnhand[$rmId])) {
                    $rmMeta[$rmId]['onhand'] = max(0, $this->rmStock($rmId));
                    $rmMeta[$rmId]['open_po'] = $this->openPo($rmId);
                    $rmMeta[$rmId]['open_pr'] = $this->openPr($rmId);

                    $rmOnhand[$rmId] = $rmMeta[$rmId]['onhand']
                        + $rmMeta[$rmId]['open_po']
                        + $rmMeta[$rmId]['open_pr'];
                }

                $avail = $rmOnhand[$rmId];
                $net = max(0, $gross - $avail);
                $rmOnhand[$rmId] = max(0, $avail - $gross);

                // Round up to whole bars, then to the supplier's pack size: a
                // mill will not cut a single bar off a bundle.
                $orderQty = $net > 0 ? $this->roundToLot($rmId, $net, $rmMeta) : 0;

                // As with products: show the origin of the supply the first time
                // the material appears, and the carried balance after that, so
                // gross − onhand − open always equals the shortage on that row.
                $isFirst = ! isset($rmSeen[$rmId]);
                $rmSeen[$rmId] = true;

                $out[] = [
                    'item_id' => $rmId, 'period' => $period,
                    'gross_req' => $gross,
                    'onhand' => $isFirst ? $rmMeta[$rmId]['onhand'] : $avail,
                    // open_po carries the requisitions too; both are supply
                    // already in the pipeline as far as planning is concerned.
                    'open_po' => $isFirst ? $rmMeta[$rmId]['open_po'] + $rmMeta[$rmId]['open_pr'] : 0,
                    'open_wo' => 0,
                    'net_req' => $orderQty,
                    'net_req_kg' => round($orderQty * ($rmMeta[$rmId]['weight'] ?? 0), 2),
                    'suggestion' => $orderQty > 0 ? 'PR' : null,
                ];

                // Ordering a full lot leaves a surplus that covers later periods.
                if ($orderQty > $net) {
                    $rmOnhand[$rmId] += $orderQty - $net;
                }
            }
        }

        return $out;
    }

    /**
     * Raise a net requirement to something a supplier will actually ship.
     *
     * Two constraints apply: the minimum order quantity, and the pack size the
     * mill sells in. Ordering 7 bars when the bundle is 25 is not an option, so
     * the quantity is rounded up rather than the shortage being left uncovered.
     *
     * Both come from the item master; where they are not set the net figure
     * stands as-is, which is the sensible default for one-off purchases.
     */
    private function roundToLot(int $rmId, int $net, array &$rmMeta): int
    {
        if (! isset($rmMeta[$rmId]['moq'])) {
            $terms = $this->supplierTerms($rmId);
            $rmMeta[$rmId]['moq'] = $terms['moq'];
            $rmMeta[$rmId]['lot'] = $terms['lot'];
        }

        $qty = max($net, $rmMeta[$rmId]['moq']);
        $lot = $rmMeta[$rmId]['lot'];

        return $lot > 1 ? (int) (ceil($qty / $lot) * $lot) : $qty;
    }

    private function bomRm(int $fgId): array
    {
        return DB::table('m_bom as b')
            ->join('m_bom_det_rm as d', 'd.id_prim', '=', 'b.id')
            ->join('m_item as i', 'i.id', '=', 'd.mat_id')
            ->where('b.item_id', $fgId)
            ->get(['d.mat_id', 'd.length_use', 'i.length as bar_length', 'i.weight'])
            ->map(fn ($r) => [
                'mat_id' => (int) $r->mat_id,
                'length_use' => (float) $r->length_use,
                'bar_length' => (float) $r->bar_length,
                'weight' => (float) $r->weight,
            ])->all();
    }

    /**
     * Independent demand for one period, per product.
     *
     * Three signals can ask for the same product, and they must not simply be
     * added — a customer order is usually the forecast coming true, so adding
     * them would buy the steel twice. The rule is the highest of:
     *
     *   - the approved monthly plan (MPP), what PPIC committed to build;
     *   - the sales forecast, the latest version per item;
     *   - firm sales orders still undelivered, which are the surest signal.
     *
     * Taking the maximum means an order that arrived after the plan was
     * approved is still covered, while an order already inside the plan does
     * not inflate it. Which signal won is recorded, because "a customer ordered
     * this" and "someone estimated this" deserve different confidence.
     *
     * @return array<int, array{qty:int, src:string, mpp:int, fc:int, so:int}>
     */
    public function grossDemand(string $period): array
    {
        $mpp = DB::table('prd_mpp')
            ->where('period', $period)->where('status', 'APPROVED')
            ->pluck('plan_qty', 'item_id');

        // Latest forecast version per item for this period.
        $forecast = DB::table('sls_forecast as f')
            ->where('f.period', $period)
            ->where('f.version', function ($q) {
                $q->from('sls_forecast as f2')->selectRaw('MAX(version)')
                    ->whereColumn('f2.item_id', 'f.item_id')
                    ->whereColumn('f2.period', 'f.period');
            })
            ->selectRaw('f.item_id, SUM(f.qty) qty')
            ->groupBy('f.item_id')
            ->pluck('qty', 'item_id');

        /*
         * Sales orders count only what is still undelivered, and only from
         * orders that are actually live — a cancelled order is not demand, and
         * a line already shipped has been satisfied.
         */
        $so = DB::table('sls_so_detail as d')
            ->join('sls_so_main as m', 'm.id', '=', 'd.main_id')
            ->whereRaw("DATE_FORMAT(COALESCE(d.due_date, m.due_date, m.date), '%Y%m') = ?", [$period])
            ->whereIn('m.status', ['APPROVED', 'SUBMITTED', 'OPEN'])
            ->selectRaw('d.item_id, SUM(GREATEST(d.qty - COALESCE(d.qty_delivered, 0), 0)) qty')
            ->groupBy('d.item_id')
            ->pluck('qty', 'item_id');

        $out = [];
        foreach (array_unique([...$mpp->keys()->all(), ...$forecast->keys()->all(), ...$so->keys()->all()]) as $itemId) {
            $m = (int) ($mpp[$itemId] ?? 0);
            $f = (int) ($forecast[$itemId] ?? 0);
            $s = (int) ($so[$itemId] ?? 0);
            $qty = max($m, $f, $s);

            if ($qty <= 0) {
                continue;
            }

            $out[(int) $itemId] = [
                'qty' => $qty,
                'src' => $qty === $s ? 'SO' : ($qty === $m ? 'MPP' : 'FORECAST'),
                'mpp' => $m, 'fc' => $f, 'so' => $s,
            ];
        }

        return $out;
    }

    /**
     * Production already committed and not yet delivered into the warehouse.
     *
     * Only released work orders count — a draft is not a commitment. And what
     * a work order still owes is its quantity less what it has already handed
     * over to finished goods; counting the full quantity would double-count the
     * part of it that is already sitting in stock.
     */
    private function openWo(int $fgId): int
    {
        /*
         * Work Order uji coba tidak dihitung. Barangnya memang dibuat, tetapi
         * dibuat untuk diukur, dibongkar, dan sebagian dikirim sebagai sampel
         * PPAP — menghitungnya sebagai pasokan berarti MRP mengira kebutuhan
         * pelanggan sudah tertutup oleh barang yang tidak akan pernah dijual.
         */
        $ordered = (int) DB::table('prd_wo_main')
            ->where('fg_id', $fgId)
            ->where('status', 2)          // RELEASED
            ->where('wo_kind', 'PROD')
            ->sum('qty');

        if ($ordered <= 0) {
            return 0;
        }

        $delivered = (int) DB::table('tr_inc_fg_det as d')
            ->join('prd_wip as w', 'w.id', '=', 'd.wip_id')
            ->join('prd_wo_main as wo', 'wo.id', '=', 'w.wo_id')
            ->where('wo.fg_id', $fgId)
            ->where('wo.status', 2)
            // Sisi pengurangnya ikut disaring, kalau tidak WO trial yang sudah
            // menyetor hasilnya akan mengurangi pasokan yang tak pernah dihitung.
            ->where('wo.wo_kind', 'PROD')
            ->sum('d.qty');

        return max(0, $ordered - $delivered);
    }

    private function fgStock(int $fgId): int
    {
        return $this->fg->stock($fgId);
    }

    private function rmStock(int $rmId): int
    {
        return (int) DB::table('wh_inc_detail')->where('item_id', $rmId)
            ->whereNotIn('serial_id', DB::table('wh_out_detail')->select('serial_id'))
            ->sum('qty');
    }

    /**
     * Purchase orders a supplier still owes us.
     *
     * These statuses are strings — DRAFT, OPEN, CLOSE, CANCELLED — and this
     * filter used to compare them against the integers 1 and 2, so it never
     * matched and MRP treated everything already on order as if it did not
     * exist. The effect was ordering the same material twice.
     *
     * DRAFT is deliberately excluded: an unapproved requisition is not a
     * commitment from anyone, so planning must not count on it arriving.
     */
    private function openPo(int $rmId): int
    {
        return (int) DB::table('prc_po_detail as d')
            ->join('prc_po_main as m', 'm.id', '=', 'd.main_id')
            ->where('d.item_id', $rmId)
            ->whereIn('m.status', ['OPEN', 'INPROGRESS'])
            ->sum(DB::raw('GREATEST(d.qty - d.qty_received, 0)'));
    }

    /**
     * Requisitions already raised but not yet turned into a purchase order.
     *
     * Without this, running MRP twice in a week raises a second requisition for
     * material the first one already covers — the buyer then has two PRs for
     * the same shortage sitting in the approval queue.
     */
    private function openPr(int $rmId): int
    {
        return (int) DB::table('prc_pr_detail as d')
            ->join('prc_pr_main as m', 'm.id', '=', 'd.main_id')
            ->leftJoin('prc_po_detail as po', 'po.pr_detail_id', '=', 'd.id')
            ->where('d.item_id', $rmId)
            ->whereIn('m.status', ['DRAFT', 'SUBMITTED', 'APPROVED'])
            ->whereNull('po.id')          // not yet converted to a PO
            ->sum('d.qty');
    }
}
