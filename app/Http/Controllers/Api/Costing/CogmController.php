<?php

namespace App\Http\Controllers\Api\Costing;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateCogmJob;
use App\Models\cst_cogm;
use App\Models\prd_wo_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

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

    /**
     * Costing every Work Order in a period grows with production volume, so it
     * is queued rather than run inside the request. The screen polls `index`.
     */
    public function run(Request $request)
    {
        $data = $request->validate([
            'period' => ['required_without:periods', 'regex:/^\d{6}$/'],
            'periods' => ['required_without:period', 'array', 'min:1', 'max:12'],
            'periods.*' => ['regex:/^\d{6}$/'],
        ]);

        // Costing is usually re-run for a quarter after a correction, so one
        // month and several are the same request with a different length.
        $periods = collect($data['periods'] ?? [$data['period']])->unique()->sort()->values()->all();

        $pending = prd_wo_main::whereIn('status', [2, 3])
            ->whereRaw("DATE_FORMAT(updated_at, '%Y%m') <= ?", [end($periods)])
            ->count();

        foreach ($periods as $p) {
            GenerateCogmJob::dispatch($p, (int) $request->user()->id);
        }
        AuditLogger::record($request, 'Hitung COGM '.implode(', ', $periods)." (bg): {$pending} WO");

        return ApiResponse::item([
            'periods' => $periods,
            'period' => $periods[0],
            'queued_wo' => $pending,
            'message' => count($periods) === 1
                ? "COGM {$periods[0]} sedang dihitung untuk {$pending} Work Order. Muat ulang beberapa saat lagi."
                : 'COGM untuk '.count($periods)." bulan sedang dihitung ({$pending} Work Order). Muat ulang beberapa saat lagi.",
        ]);
    }
}
