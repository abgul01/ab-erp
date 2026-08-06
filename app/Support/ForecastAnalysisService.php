<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Forecast accuracy analysis: MAPE, BIAS per item/period.
 *
 * LLD §4.4
 */
class ForecastAnalysisService
{
    /**
     * Compute MAPE and BIAS for a period range.
     *
     * MAPE = avg(|actual - forecast| / actual) * 100
     * BIAS = Σ(forecast - actual) / n  (positive = over-forecast)
     *
     * @return array{mape: float, bias: float, items: array}
     */
    public function analyze(string $periodFrom, string $periodTo): array
    {
        $forecasts = DB::table('sls_forecast as f')
            ->join('m_item as i', 'i.id', '=', 'f.item_id')
            ->whereBetween('f.period', [$periodFrom, $periodTo])
            ->where('f.version', function ($q) {
                $q->from('sls_forecast as f2')
                    ->selectRaw('MAX(version)')
                    ->whereColumn('f2.item_id', 'f.item_id')
                    ->whereColumn('f2.period', 'f.period')
                    ->whereColumn('f2.cus_id', 'f.cus_id');
            })
            ->select('f.*', 'i.code as item_code', 'i.part_name')
            ->orderBy('f.period')
            ->get();

        if ($forecasts->isEmpty()) {
            return ['mape' => 0, 'bias' => 0, 'items' => []];
        }

        // A sales order is dated, not stamped with a period, so the bucket is
        // derived here to line up with the forecast's YYYYMM.
        $period = "DATE_FORMAT(m.date, '%Y%m')";

        $actuals = DB::table('sls_so_detail as d')
            ->join('sls_so_main as m', 'm.id', '=', 'd.main_id')
            ->whereRaw("{$period} BETWEEN ? AND ?", [$periodFrom, $periodTo])
            ->whereIn('m.status', ['APPROVED', 'CLOSED'])
            ->select(DB::raw("{$period} as period"), 'd.item_id', DB::raw('SUM(d.qty) as total_qty'))
            ->groupBy(DB::raw($period), 'd.item_id')
            ->get()
            ->keyBy(fn ($r) => $r->period.'_'.$r->item_id);

        $items = [];
        $totalApe = 0;
        $totalBias = 0;
        $count = 0;

        foreach ($forecasts as $f) {
            $key = $f->period.'_'.$f->item_id;
            $actual = (int) (($actuals[$key] ?? false)?->total_qty ?? 0);
            $forecast = (int) $f->qty;

            $diff = $forecast - $actual;
            $ape = $actual > 0 ? abs($diff / $actual) * 100 : ($forecast > 0 ? 100 : 0);

            $totalApe += $ape;
            $totalBias += $diff;
            $count++;

            $items[] = [
                'period' => $f->period,
                'item_id' => $f->item_id,
                'item_code' => $f->item_code,
                'item_name' => $f->part_name,
                'forecast_qty' => $forecast,
                'actual_qty' => $actual,
                'diff' => $diff,
                'ape_pct' => round($ape, 1),
            ];
        }

        return [
            'mape' => $count > 0 ? round($totalApe / $count, 2) : 0,
            'bias' => $count > 0 ? round($totalBias / $count, 2) : 0,
            'items' => $items,
        ];
    }
}
