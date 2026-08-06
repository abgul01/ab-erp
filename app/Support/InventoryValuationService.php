<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * What the stock on the floor is worth, and which products actually make money.
 *
 * The balance sheet already carries inventory as a total, because journals move
 * with every receipt and issue. What it cannot answer is "which of these
 * products is losing us money" — for that, revenue and cost have to meet on the
 * same line, and that is what this does.
 *
 * Three valuation bases, each stated rather than implied:
 *
 *   RM  — weighted-average landed cost per kilogram, from the import cost
 *         sheets already allocated to receipts, times the bar's own weight.
 *         Materials that never went through a cost sheet fall back to the
 *         average purchase price, and rows say which basis they used.
 *   WIP — material cost only. Pieces are cut and part-processed; labour and
 *         overhead are absorbed at COGM when the lot reaches finished goods, so
 *         charging them here would count them twice.
 *   FG  — the actual unit cost frozen on each lot when it was received, which
 *         is COGM's own number. No averaging, no re-derivation.
 *
 * Margin is taken lot by lot: an invoice line points at the delivery line, the
 * delivery line names the lot it shipped, and the lot carries the cost it was
 * built at. Averages would hide exactly the product this report exists to find.
 *
 * PRD §7 (Inventory Valuation & analisis margin)
 */
class InventoryValuationService
{
    /**
     * Average landed cost per piece for every raw material that has one.
     *
     * @return array<int, array{unit_cost: float, basis: string}>
     */
    public function rmUnitCosts(): array
    {
        $out = [];

        /*
         * Landed cost is allocated per receipt line and expressed per kilogram,
         * so it becomes a per-piece figure only through the bar's own weight.
         */
        $landed = DB::table('prc_cost_alloc as a')
            ->join('prc_gr_detail as g', 'g.id', '=', 'a.gr_detail_id')
            ->join('m_item as i', 'i.id', '=', 'g.item_id')
            ->groupBy('g.item_id', 'i.weight')
            ->selectRaw('g.item_id, i.weight, SUM(a.amount) as amount, SUM(g.w_total) as kg')
            ->get();

        foreach ($landed as $r) {
            $kg = (float) $r->kg;
            if ($kg <= 0) {
                continue;
            }
            $out[(int) $r->item_id] = [
                'unit_cost' => round((float) $r->amount / $kg * (float) $r->weight, 2),
                'basis' => 'LANDED',
            ];
        }

        // Anything never costed through an import sheet: what it was bought for.
        $po = DB::table('prc_po_detail as d')
            ->join('prc_po_main as m', 'm.id', '=', 'd.main_id')
            ->whereNotIn('m.status', ['CANCELLED', 'DRAFT'])
            ->groupBy('d.item_id')
            ->selectRaw('d.item_id, SUM(d.price * d.qty) as amount, SUM(d.qty) as qty')
            ->get();

        foreach ($po as $r) {
            $id = (int) $r->item_id;
            if (isset($out[$id]) || (float) $r->qty <= 0) {
                continue;
            }
            $out[$id] = ['unit_cost' => round((float) $r->amount / (float) $r->qty, 2), 'basis' => 'PO'];
        }

        return $out;
    }

    /**
     * Raw material on the rack: bars received and not yet issued.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rm(): array
    {
        $costs = $this->rmUnitCosts();

        $rows = DB::table('wh_inc_detail as w')
            ->join('m_item as i', 'i.id', '=', 'w.item_id')
            ->whereNotIn('w.serial_id', DB::table('wh_out_detail')->select('serial_id'))
            ->groupBy('w.item_id', 'i.code', 'i.part_name', 'i.weight')
            ->selectRaw('w.item_id, i.code, i.part_name, i.weight, SUM(w.qty) as qty')
            ->get();

        return $rows->map(function ($r) use ($costs) {
            $c = $costs[(int) $r->item_id] ?? ['unit_cost' => 0.0, 'basis' => 'NONE'];

            return [
                'item_id' => (int) $r->item_id,
                'code' => $r->code,
                'part_name' => $r->part_name,
                'qty' => (int) $r->qty,
                'unit_cost' => $c['unit_cost'],
                'basis' => $c['basis'],
                'value' => round((int) $r->qty * $c['unit_cost'], 2),
            ];
        })->sortByDesc('value')->values()->all();
    }

    /**
     * Work in progress: pieces cut for a work order that have not reached the
     * finished-goods warehouse.
     *
     * Counted as cut-minus-received rather than by adding up pallets at every
     * step — the same physical piece appears on a pallet at each process it
     * passes, and summing those would value it three times over.
     *
     * @return array<int, array<string, mixed>>
     */
    public function wip(): array
    {
        $cut = DB::table('tr_cut_pal_pr as cp')
            ->join('tr_cut_main as cm', 'cm.id', '=', 'cp.cut_id')
            ->join('prd_wip as w', 'w.id', '=', 'cm.wip_id')
            ->join('prd_wo_main as wo', 'wo.id', '=', 'w.wo_id')
            ->whereIn('wo.status', [2, 3])              // released & finished
            ->groupBy('wo.id', 'wo.code', 'wo.fg_id', 'wo.qty')
            ->selectRaw('wo.id as wo_id, wo.code as wo_code, wo.fg_id, wo.qty as wo_qty, SUM(cp.qty) as cut_qty')
            ->get();

        $received = DB::table('tr_inc_fg_det as d')
            ->join('prd_wip as w', 'w.id', '=', 'd.wip_id')
            ->groupBy('w.wo_id')
            ->selectRaw('w.wo_id, SUM(d.qty) as qty')
            ->pluck('qty', 'wo_id');

        $items = DB::table('m_item')->pluck('part_name', 'id');
        $codes = DB::table('m_item')->pluck('code', 'id');
        $unitCost = $this->wipMaterialUnitCosts();

        $out = [];
        foreach ($cut as $r) {
            $qty = (int) $r->cut_qty - (int) ($received[$r->wo_id] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $cost = $unitCost[(int) $r->wo_id] ?? 0.0;
            $out[] = [
                'wo_id' => (int) $r->wo_id,
                'wo_code' => $r->wo_code,
                'item_id' => (int) $r->fg_id,
                'code' => $codes[$r->fg_id] ?? '—',
                'part_name' => $items[$r->fg_id] ?? '—',
                'qty' => $qty,
                'unit_cost' => $cost,
                'value' => round($qty * $cost, 2),
            ];
        }

        usort($out, fn ($a, $b) => $b['value'] <=> $a['value']);

        return $out;
    }

    /**
     * Material cost per piece for each work order, from its own COGM where one
     * has been calculated.
     *
     * @return array<int, float>
     */
    private function wipMaterialUnitCosts(): array
    {
        return DB::table('cst_cogm as c')
            ->join('prd_wo_main as w', 'w.id', '=', 'c.wo_id')
            ->where('w.qty', '>', 0)
            ->selectRaw('c.wo_id, c.material_cost / w.qty as unit_cost')
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->wo_id => round((float) $r->unit_cost, 2)])
            ->all();
    }

    /**
     * Finished goods, lot by lot, at the cost each lot was received at.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fg(): array
    {
        $out = DB::table('tr_out_fg_det')
            ->select('fg_code', DB::raw('SUM(qty) as used'))->groupBy('fg_code');

        $rows = DB::table('tr_inc_fg_det as d')
            ->join('m_item as i', 'i.id', '=', 'd.item_id')
            ->leftJoinSub($out, 'o', fn ($j) => $j->on(
                DB::raw('CONVERT(o.fg_code USING utf8mb4) COLLATE utf8mb4_general_ci'),
                '=',
                DB::raw('CONVERT(d.code USING utf8mb4) COLLATE utf8mb4_general_ci')
            ))
            ->where('d.status', 'ACTIVE')
            ->groupBy('d.item_id', 'i.code', 'i.part_name')
            ->selectRaw('d.item_id, i.code, i.part_name,
                SUM(d.qty - COALESCE(o.used, 0)) as qty,
                SUM((d.qty - COALESCE(o.used, 0)) * d.unit_cost) as value')
            ->havingRaw('SUM(d.qty - COALESCE(o.used, 0)) > 0')
            ->get();

        return $rows->map(fn ($r) => [
            'item_id' => (int) $r->item_id,
            'code' => $r->code,
            'part_name' => $r->part_name,
            'qty' => (int) $r->qty,
            'unit_cost' => (int) $r->qty > 0 ? round((float) $r->value / (int) $r->qty, 2) : 0.0,
            'value' => round((float) $r->value, 2),
        ])->sortByDesc('value')->values()->all();
    }

    /** The three categories with their totals, for a report header. */
    public function valuation(): array
    {
        $rm = $this->rm();
        $wip = $this->wip();
        $fg = $this->fg();

        $sum = fn (array $rows) => round(array_sum(array_column($rows, 'value')), 2);

        return [
            'as_of' => now()->toDateString(),
            'rm' => $rm,
            'wip' => $wip,
            'fg' => $fg,
            'total' => [
                'rm' => $sum($rm),
                'wip' => $sum($wip),
                'fg' => $sum($fg),
                'all' => round($sum($rm) + $sum($wip) + $sum($fg), 2),
            ],
        ];
    }

    /**
     * Margin per product for a period, from invoices actually issued.
     *
     * Cost follows the lot that was shipped, not an average: two lots of the
     * same product built in different months cost different amounts, and the
     * expensive one is usually why a margin looks wrong.
     *
     * @param  string  $period  YYYYMM
     */
    public function margin(string $period): array
    {
        $rows = DB::table('sls_inv_detail as d')
            ->join('sls_inv_main as m', 'm.id', '=', 'd.main_id')
            ->join('m_item as i', 'i.id', '=', 'd.item_id')
            ->leftJoin('m_product_family as f', 'f.id', '=', 'i.family_id')
            ->leftJoin('sls_do_detail as dd', 'dd.id', '=', 'd.do_detail_id')
            ->leftJoin('tr_inc_fg_det as lot', DB::raw('CONVERT(lot.code USING utf8mb4) COLLATE utf8mb4_general_ci'),
                '=', DB::raw('CONVERT(dd.fg_code USING utf8mb4) COLLATE utf8mb4_general_ci'))
            ->whereRaw("DATE_FORMAT(m.date, '%Y%m') = ?", [$period])
            ->whereNotIn('m.status', ['CANCELLED', 'DRAFT'])
            ->groupBy('d.item_id', 'i.code', 'i.part_name', 'i.family_id', 'f.code', 'f.name')
            ->selectRaw('d.item_id, i.code, i.part_name,
                i.family_id, f.code as family_code, f.name as family_name,
                SUM(d.qty) as qty,
                SUM(d.amount) as revenue,
                SUM(d.qty * COALESCE(lot.unit_cost, 0)) as cogs,
                SUM(CASE WHEN lot.id IS NULL THEN d.qty ELSE 0 END) as qty_uncosted')
            ->get();

        $items = $rows->map(function ($r) {
            $revenue = round((float) $r->revenue, 2);
            $cogs = round((float) $r->cogs, 2);
            $margin = round($revenue - $cogs, 2);

            return [
                'item_id' => (int) $r->item_id,
                'code' => $r->code,
                'part_name' => $r->part_name,
                'family_id' => $r->family_id ? (int) $r->family_id : null,
                'family' => $r->family_code ? "{$r->family_code} — {$r->family_name}" : null,
                'qty' => (int) $r->qty,
                'revenue' => $revenue,
                'cogs' => $cogs,
                'margin' => $margin,
                'margin_pct' => $revenue > 0 ? round($margin / $revenue * 100, 2) : null,
                // Pieces whose lot cost could not be traced: the margin on those
                // is overstated, and saying so is better than quietly averaging.
                'qty_uncosted' => (int) $r->qty_uncosted,
            ];
        })->sortBy('margin_pct')->values()->all();

        $revenue = round(array_sum(array_column($items, 'revenue')), 2);
        $cogs = round(array_sum(array_column($items, 'cogs')), 2);

        /*
         * Ringkasan per keluarga produk. Manajemen jarang bertanya "berapa
         * margin BRK-TUBE-42-G2"; yang ditanyakan adalah apakah keluarga
         * bracket ini menguntungkan. Part tanpa keluarga dikumpulkan apa adanya
         * — menyembunyikannya akan membuat totalnya tidak cocok.
         */
        $families = collect($items)
            ->groupBy(fn ($i) => $i['family'] ?? '(tanpa keluarga)')
            ->map(function ($group, $name) {
                $rev = round($group->sum('revenue'), 2);
                $cost = round($group->sum('cogs'), 2);

                return [
                    'family' => $name,
                    'items' => $group->count(),
                    'qty' => (int) $group->sum('qty'),
                    'revenue' => $rev,
                    'cogs' => $cost,
                    'margin' => round($rev - $cost, 2),
                    'margin_pct' => $rev > 0 ? round(($rev - $cost) / $rev * 100, 2) : null,
                ];
            })
            ->sortBy('margin_pct')
            ->values()
            ->all();

        return [
            'period' => $period,
            'items' => $items,
            'families' => $families,
            'total' => [
                'revenue' => $revenue,
                'cogs' => $cogs,
                'margin' => round($revenue - $cogs, 2),
                'margin_pct' => $revenue > 0 ? round(($revenue - $cogs) / $revenue * 100, 2) : null,
                'qty_uncosted' => array_sum(array_column($items, 'qty_uncosted')),
            ],
        ];
    }
}
