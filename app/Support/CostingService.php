<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Cost of Goods Manufactured (COGM) per Work Order — standard costing.
 *
 *   material = qty × Σ BOM RM (length_use × weight/mm × RM cost/kg)
 *   labor    = Σ routing ops (op hours × LABOR rate for that process)
 *   foh      = Σ routing ops (op hours × FOH rate for that process)
 *   subcont  = qty sent on subcont DNs for the WO × subcont PO price
 *   total    = material + labor + foh + subcont − scrap recovery
 *   unit     = total / qty
 *
 * Rates come from cst_rate (per period, per process, with a process-agnostic
 * fallback); RM cost/kg from the item's PO price, both with sane defaults so a
 * run always produces a number.
 */
class CostingService
{
    private const DEFAULT_LABOR_RATE = 25000.0;   // Rp/hour
    private const DEFAULT_FOH_RATE = 40000.0;      // Rp/hour
    private const DEFAULT_RM_KG = 15000.0;         // Rp/kg
    private const SCRAP_VALUE_KG = 3000.0;         // Rp/kg recovered

    private array $rateCache = [];

    /** @return array{material_cost:float,labor_cost:float,foh_cost:float,subcont_cost:float,scrap_recovery:float,total:float,unit_cost:float} */
    public function cogmForWo(object $wo, string $period): array
    {
        $qty = max(1, (int) $wo->qty);
        $planner = new PlanningService;

        $material = round($qty * $this->materialPerPc((int) $wo->fg_id), 2);

        $labor = 0.0;
        $foh = 0.0;
        foreach ($planner->routing((int) $wo->fg_id, $wo->process_main_id ? (int) $wo->process_main_id : null) as $op) {
            $machine = $op['machines'][0]['machine_id'] ?? null;
            $cycle = $planner->cycleFor((int) $wo->fg_id, (int) $op['proc_id'], $machine);
            $hours = ($cycle * $qty) / 3600;
            $labor += $hours * $this->rate($period, 'LABOR', (int) $op['proc_id']);
            $foh += $hours * $this->rate($period, 'FOH', (int) $op['proc_id']);
        }

        $subcont = $this->subcontCost((int) $wo->id);
        $scrap = $this->scrapRecovery((int) $wo->id);
        $total = round($material + $labor + $foh + $subcont - $scrap, 2);

        return [
            'material_cost' => $material,
            'labor_cost' => round($labor, 2),
            'foh_cost' => round($foh, 2),
            'subcont_cost' => round($subcont, 2),
            'scrap_recovery' => round($scrap, 2),
            'total' => $total,
            'unit_cost' => round($total / $qty, 4),
        ];
    }

    /** Standard material cost of one finished piece from its BOM RM lines. */
    private function materialPerPc(int $fgId): float
    {
        $lines = DB::table('m_bom as b')
            ->join('m_bom_det_rm as d', 'd.id_prim', '=', 'b.id')
            ->join('m_item as i', 'i.id', '=', 'd.mat_id')
            ->where('b.item_id', $fgId)
            ->get(['d.mat_id', 'd.length_use', 'i.weight', 'i.length']);

        $cost = 0.0;
        foreach ($lines as $l) {
            $bar = max(1.0, (float) $l->length);
            $weightPerMm = (float) $l->weight / $bar;                 // kg per mm of bar
            $kg = (float) $l->length_use * $weightPerMm;              // kg used per fg pc
            $cost += $kg * $this->rmCostPerKg((int) $l->mat_id);
        }

        return $cost;
    }

    /** RM cost per kg: latest PO price_kg for the item, else default. */
    private function rmCostPerKg(int $rmId): float
    {
        $price = DB::table('prc_po_detail')->where('item_id', $rmId)->where('price_kg', '>', 0)
            ->orderByDesc('id')->value('price_kg');

        return $price ? (float) $price : self::DEFAULT_RM_KG;
    }

    /** Rate for a process in a period: process-specific first, then general, then default. */
    private function rate(string $period, string $type, int $procId): float
    {
        $key = "{$period}|{$type}";
        if (! isset($this->rateCache[$key])) {
            $this->rateCache[$key] = DB::table('cst_rate')->where('period', $period)->where('rate_type', $type)
                ->get(['process_id', 'rate_per_hour']);
        }
        $rows = $this->rateCache[$key];
        $specific = $rows->firstWhere('process_id', $procId);
        if ($specific) {
            return (float) $specific->rate_per_hour;
        }
        $general = $rows->firstWhere('process_id', null);
        if ($general) {
            return (float) $general->rate_per_hour;
        }

        return $type === 'LABOR' ? self::DEFAULT_LABOR_RATE : self::DEFAULT_FOH_RATE;
    }

    /** Subcontract cost booked to the WO (qty sent × subcont PO unit price). */
    private function subcontCost(int $woId): float
    {
        return (float) DB::table('sub_dn_detail as dd')
            ->join('sub_dn_main as dm', 'dm.id', '=', 'dd.main_id')
            ->leftJoin('prc_po_detail as pd', function ($j) {
                $j->on('pd.main_id', '=', 'dm.po_id')->on('pd.item_id', '=', 'dd.item_id');
            })
            ->where('dd.wo_id', $woId)
            ->sum(DB::raw('dd.qty * COALESCE(pd.price, 0)'));
    }

    /** Scrap recovered on the WO's cutting (leftover bars below usable length). */
    private function scrapRecovery(int $woId): float
    {
        // scrapped booked serials carry their remaining length; value it by weight
        $rows = DB::table('prd_wo_serial_rm as s')
            ->join('prd_wo_detail_rm as d', 'd.id', '=', 's.detail_id')
            ->join('prd_wo_main as w', 'w.id', '=', 'd.main_id')
            ->join('m_item as i', 'i.id', '=', 'd.rm_id')
            ->where('w.id', $woId)->where('s.scrap', 1)
            ->get(['s.length_rem', 'i.weight', 'i.length']);

        $val = 0.0;
        foreach ($rows as $r) {
            $bar = max(1.0, (float) $r->length);
            $kg = (float) $r->length_rem * ((float) $r->weight / $bar);
            $val += $kg * self::SCRAP_VALUE_KG;
        }

        return $val;
    }
}
