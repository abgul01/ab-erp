<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Finished-goods warehouse maths, shared by the FG stock-in screen and the
 * Delivery Order.
 *
 * Every routing ends with the terminal "FG" marker step (PlanningService keeps
 * that marker out of the MES flow). A piece is ready to enter the FG warehouse
 * once it clears the last REAL step before FG:
 *   - a processing pallet (tr_pro_pal_pr, FULL) at the routing's last real step, or
 *   - for a cut-only routing ([CUT, FG]), the cutting pallet (tr_cut_pal_pr).
 * Receiving it writes tr_inc_fg (carrying the lot = WIP code + pallet code).
 *
 * Stock is tracked per lot: each tr_inc_fg_det is one lot batch, and the DO ships
 * against specific lots (tr_out_fg_det.fg_code = the lot's tr_inc_fg_det.code).
 * Codes carry mixed collations, so text joins are CONVERTed to utf8mb4_general_ci.
 */
class FgStockService
{
    private const COLL = 'utf8mb4_general_ci';

    /**
     * Finished pallets waiting to be received into the FG warehouse, from both
     * processing and cut-only routings. One row per pallet, carrying its lot.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function availablePallets(?int $itemId = null)
    {
        $coll = self::COLL;
        $fgProc = PlanningService::fgProcId();

        // last real (non-FG) step per routing template, and its real-step count
        $realDet = DB::table('m_process_main_det')
            ->when($fgProc, fn ($q) => $q->where('proc_id', '<>', $fgProc));
        $lastStep = (clone $realDet)->groupBy('main_id')
            ->select('main_id', DB::raw('MAX(sequence) as last_seq'), DB::raw('COUNT(*) as real_cnt'));

        $notStocked = "CONVERT(pp.code USING utf8mb4) COLLATE {$coll} NOT IN (
            SELECT CONVERT(pal_pro_code USING utf8mb4) COLLATE {$coll} FROM tr_inc_fg_det
        )";

        // 1) processing pallets at the routing's last real step
        $pro = DB::table('tr_pro_pal_pr as pp')
            ->join('tr_pro_main as pm', 'pm.id', '=', 'pp.pro_id')
            ->join('prd_wip as w', 'w.id', '=', 'pm.wip_id')
            ->join('prd_wo_main as wm', 'wm.id', '=', 'w.wo_id')
            ->joinSub($lastStep, 'ls', 'ls.main_id', '=', 'wm.process_main_id')
            ->join('m_process_main_det as bd', function ($j) {
                $j->on('bd.main_id', '=', 'wm.process_main_id')
                    ->on('bd.sequence', '=', 'ls.last_seq')
                    ->on('bd.proc_id', '=', 'pp.process_id');
            })
            ->join('m_item as i', 'i.id', '=', 'wm.fg_id')
            ->leftJoin('m_contacts as cu', 'cu.id', '=', 'wm.customer_id')
            ->where('pp.status', 'FULL')
            ->whereRaw($notStocked)
            ->when($itemId, fn ($q) => $q->where('wm.fg_id', $itemId))
            ->get(['pp.id', 'pp.code', 'pp.qty', 'pp.cut_id', 'pp.process_id',
                'w.id as wip_id', 'w.code as wip_code', 'wm.fg_id as item_id', 'i.code as item_code', 'i.part_name',
                DB::raw('cu.company_n as customer'), DB::raw('i.length as size')])
            ->map(fn ($r) => (object) ((array) $r + ['source' => 'PRO']));

        // 2) cut pallets for cut-only routings (one real step: cutting → FG)
        $cutNotStocked = str_replace('pp.code', 'cp.code', $notStocked);
        $cut = DB::table('tr_cut_pal_pr as cp')
            ->join('tr_cut_main as cm', 'cm.id', '=', 'cp.cut_id')
            ->join('prd_wip as w', 'w.id', '=', 'cm.wip_id')
            ->join('prd_wo_main as wm', 'wm.id', '=', 'w.wo_id')
            ->joinSub($lastStep, 'ls', function ($j) {
                $j->on('ls.main_id', '=', 'wm.process_main_id')->where('ls.real_cnt', '=', 1);
            })
            ->join('m_item as i', 'i.id', '=', 'wm.fg_id')
            ->leftJoin('m_contacts as cu', 'cu.id', '=', 'wm.customer_id')
            ->whereRaw($cutNotStocked)
            ->when($itemId, fn ($q) => $q->where('wm.fg_id', $itemId))
            ->get(['cp.id', 'cp.code', 'cp.qty', 'cp.cut_id', 'cp.process_id',
                'w.id as wip_id', 'w.code as wip_code', 'wm.fg_id as item_id', 'i.code as item_code', 'i.part_name',
                DB::raw('cu.company_n as customer'), DB::raw('i.length as size')])
            ->map(fn ($r) => (object) ((array) $r + ['source' => 'CUT']));

        return $pro->merge($cut)->sortBy('id')->values();
    }

    /**
     * FG lots on hand for an item (or all), oldest first (FIFO): each received
     * batch with its remaining qty after issues. Used for lot selection on the DO.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function availableLots(?int $itemId = null)
    {
        $coll = self::COLL;

        $out = DB::table('tr_out_fg_det')
            ->select('fg_code', DB::raw('SUM(qty) as used'))->groupBy('fg_code');

        $rows = DB::table('tr_inc_fg_det as d')
            ->join('tr_inc_fg_main as m', 'm.id', '=', 'd.main_id')
            ->leftJoinSub($out, 'o', fn ($j) => $j->on(
                DB::raw("CONVERT(o.fg_code USING utf8mb4) COLLATE {$coll}"),
                '=',
                DB::raw("CONVERT(d.code USING utf8mb4) COLLATE {$coll}")
            ))
            ->when($itemId, fn ($q) => $q->where('d.item_id', $itemId))
            ->orderBy('m.date')->orderBy('d.id')
            ->get([
                'd.id', 'd.code as lot_code', 'd.item_id', 'd.pal_pro_code', 'd.wip_id', 'd.qty',
                'm.code as receipt_code', 'm.date',
                DB::raw('COALESCE(o.used, 0) as used'),
            ]);

        return $rows->map(function ($r) {
            $r->qty = (int) $r->qty;
            $r->used = (int) $r->used;
            $r->remaining = $r->qty - $r->used;

            return $r;
        })->filter(fn ($r) => $r->remaining > 0)->values();
    }

    /** On-hand finished pieces per item id: Σ stocked − Σ issued. */
    public function stockByItem(): array
    {
        $in = DB::table('tr_inc_fg_det')->groupBy('item_id')
            ->select('item_id', DB::raw('SUM(qty) as q'))->pluck('q', 'item_id');
        $out = DB::table('tr_out_fg_det')->groupBy('item_id')
            ->select('item_id', DB::raw('SUM(qty) as q'))->pluck('q', 'item_id');

        $stock = [];
        foreach ($in as $itemId => $q) {
            $stock[$itemId] = (int) $q - (int) ($out[$itemId] ?? 0);
        }

        return $stock;
    }

    /** On-hand finished pieces for one item. */
    public function stock(int $itemId): int
    {
        $in = (int) DB::table('tr_inc_fg_det')->where('item_id', $itemId)->sum('qty');
        $out = (int) DB::table('tr_out_fg_det')->where('item_id', $itemId)->sum('qty');

        return $in - $out;
    }
}
