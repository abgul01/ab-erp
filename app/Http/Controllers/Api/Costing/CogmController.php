<?php

namespace App\Http\Controllers\Api\Costing;

use App\Http\Controllers\Controller;
use App\Models\cst_cogm;
use App\Models\prd_wo_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\CostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cost of Goods Manufactured. "Hitung" computes standard COGM for every Work
 * Order closed in the period (material + labor + FOH + subcont − scrap) and
 * stores it in cst_cogm (upsert per period+WO); the screen then lists it.
 */
class CogmController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->query('period', now()->format('Ym'));
        $rows = cst_cogm::with('wo.fg')->where('period', $period)->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'wo_id' => $c->wo_id, 'wo_code' => $c->wo?->code,
                'item_code' => $c->wo?->fg?->code, 'part_name' => $c->wo?->fg?->part_name,
                'qty' => (int) ($c->wo?->qty ?? 0),
                'material_cost' => (float) $c->material_cost, 'labor_cost' => (float) $c->labor_cost,
                'foh_cost' => (float) $c->foh_cost, 'subcont_cost' => (float) $c->subcont_cost,
                'scrap_recovery' => (float) $c->scrap_recovery, 'total' => (float) $c->total,
                'unit_cost' => (float) $c->unit_cost,
            ])->values();

        return ApiResponse::item(['period' => $period, 'rows' => $rows]);
    }

    public function run(Request $request)
    {
        $data = $request->validate(['period' => ['required', 'regex:/^\d{6}$/']]);
        $period = $data['period'];

        // WOs closed in the period (fall back to all non-cancelled if none closed)
        $wos = prd_wo_main::whereIn('status', [2, 3])
            ->whereRaw("DATE_FORMAT(updated_at, '%Y%m') <= ?", [$period])
            ->get();

        $svc = new CostingService;
        $n = DB::transaction(function () use ($wos, $period, $svc, $request) {
            $count = 0;
            foreach ($wos as $wo) {
                $c = $svc->cogmForWo($wo, $period);
                cst_cogm::updateOrCreate(['period' => $period, 'wo_id' => $wo->id], $c);
                $count++;
            }
            AuditLogger::record($request, "Hitung COGM {$period}: {$count} WO");

            return $count;
        });

        return $this->index($request);
    }
}
