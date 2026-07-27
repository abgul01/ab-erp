<?php

namespace App\Http\Controllers\Api\Production;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Actual vs plan, per machine per day. The plan is the MPS lot (prd_mps); the
 * actual is what the floor really reported — cut pieces (tr_cut_serial) and
 * processed pieces (tr_pro_detail) — with the NG found on that machine that day.
 */
class MesReportController extends Controller
{
    public function vsMps(Request $request)
    {
        $data = $request->validate(['period' => ['required', 'string', 'regex:/^\d{6}$/']]);
        $like = substr($data['period'], 0, 4) . '-' . substr($data['period'], 4, 2) . '-%';

        $rows = [];
        $put = function (&$rows, $machineId, $date, $field, $qty) {
            $k = ((int) $machineId) . '|' . substr((string) $date, 0, 10);
            $rows[$k] ??= ['machine_id' => (int) $machineId, 'date' => substr((string) $date, 0, 10),
                'plan' => 0, 'cut' => 0, 'process' => 0, 'ng' => 0];
            $rows[$k][$field] += (int) $qty;
        };

        // plan: MPS lots
        foreach (DB::table('prd_mps')->where('plan_date', 'like', $like)->whereNotNull('machine_id')
            ->selectRaw('machine_id, plan_date d, SUM(qty) q')->groupBy('machine_id', 'plan_date')->get() as $r) {
            $put($rows, $r->machine_id, $r->d, 'plan', $r->q);
        }

        // actual: cutting
        foreach (DB::table('tr_cut_serial as cs')
            ->join('tr_cut_detail as cd', 'cd.id', '=', 'cs.detail_id')
            ->join('tr_cut_main as cm', 'cm.id', '=', 'cd.main_id')
            ->where('cm.date', 'like', $like)
            ->selectRaw('cd.machine_id, cm.date d, SUM(cs.qty) q')->groupBy('cd.machine_id', 'cm.date')->get() as $r) {
            $put($rows, $r->machine_id, $r->d, 'cut', $r->q);
        }

        // actual: processing
        foreach (DB::table('tr_pro_detail as pd')
            ->join('tr_pro_main as pm', 'pm.id', '=', 'pd.main_id')
            ->where('pm.date', 'like', $like)
            ->selectRaw('pd.machine_id, pm.date d, SUM(COALESCE(pd.qty_half,0) + COALESCE(pd.qty_full,0)) q')
            ->groupBy('pd.machine_id', 'pm.date')->get() as $r) {
            $put($rows, $r->machine_id, $r->d, 'process', $r->q);
        }

        // NG decided on that machine/day
        foreach (DB::table('tr_ng_cut as n')->join('tr_ab_cut_main as a', 'a.id', '=', 'n.main_id')
            ->where('a.date', 'like', $like)
            ->selectRaw('a.machine_id, a.date d, SUM(n.qty) q')->groupBy('a.machine_id', 'a.date')->get() as $r) {
            $put($rows, $r->machine_id, $r->d, 'ng', $r->q);
        }
        foreach (DB::table('tr_ng_pro as n')->join('tr_ab_pro as a', 'a.id', '=', 'n.main_id')
            ->where('a.date', 'like', $like)
            ->selectRaw('a.machine_id, DATE(a.date) d, SUM(n.qty) q')->groupBy('a.machine_id', DB::raw('DATE(a.date)'))->get() as $r) {
            $put($rows, $r->machine_id, $r->d, 'ng', $r->q);
        }

        $machines = DB::table('m_machine')->pluck('code', 'id');
        $out = collect(array_values($rows))->map(function ($r) use ($machines) {
            $r['machine'] = $machines[$r['machine_id']] ?? null;
            $r['actual'] = $r['cut'] + $r['process'];
            $r['diff'] = $r['actual'] - $r['plan'];

            return $r;
        })->sortBy([['date', 'asc'], ['machine', 'asc']])->values();

        return ApiResponse::collection($out);
    }
}
