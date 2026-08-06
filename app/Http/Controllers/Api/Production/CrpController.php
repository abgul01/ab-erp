<?php

namespace App\Http\Controllers\Api\Production;

use App\Http\Controllers\Controller;
use App\Jobs\RunCrpJob;
use App\Models\prd_crp;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\CrpService;
use Illuminate\Http\Request;

class CrpController extends Controller
{
    public function index(Request $request)
    {
        $rows = prd_crp::with('process', 'machine')
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        $crp = prd_crp::with('process', 'machine')->findOrFail($id);

        return ApiResponse::item($crp);
    }

    /**
     * Queued: a run walks every approved MPS line through its routing, which
     * outgrows a request once the order book is full. `sync` forces it inline
     * for a small period the planner wants to see immediately.
     */
    /**
     * Queued: a run walks every approved MPS line through its routing, which
     * outgrows a request once the order book is full. `sync` forces it inline
     * for a small period the planner wants to see immediately.
     *
     * Accepts one month or several — capacity is usually reviewed over a
     * quarter, and asking the planner to run it three times is busywork.
     */
    public function run(Request $request)
    {
        $data = $request->validate([
            'period' => ['required_without:periods', 'string', 'regex:/^\d{6}$/'],
            'periods' => ['required_without:period', 'array', 'min:1', 'max:12'],
            'periods.*' => ['string', 'regex:/^\d{6}$/'],
            'sync' => ['nullable', 'boolean'],
        ]);

        $periods = collect($data['periods'] ?? [$data['period']])->unique()->sort()->values()->all();
        $label = implode(', ', $periods);

        if ($request->boolean('sync')) {
            $results = [];
            foreach ($periods as $p) {
                $results[$p] = app(CrpService::class)->run($p);
            }
            AuditLogger::record($request, "Run CRP {$label}");

            // A single period keeps the original shape so existing callers and
            // tests are unaffected; several return one result per month.
            return ApiResponse::item(count($periods) === 1 ? reset($results) : ['periods' => $periods, 'runs' => $results]);
        }

        foreach ($periods as $p) {
            RunCrpJob::dispatch($p, (int) $request->user()->id);
        }
        AuditLogger::record($request, "Run CRP {$label} (bg)");

        return ApiResponse::item([
            'periods' => $periods,
            'period' => $periods[0],
            'run_id' => null,
            'queued' => true,
            'message' => count($periods) === 1
                ? "CRP {$periods[0]} sedang dihitung. Muat ulang beberapa saat lagi."
                : 'CRP untuk '.count($periods).' bulan sedang dihitung. Muat ulang beberapa saat lagi.',
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $crp = prd_crp::findOrFail($id);
        $crp->delete();
        AuditLogger::record($request, "Delete CRP run {$crp->id}");

        return ApiResponse::item(['message' => 'CRP run dihapus.']);
    }
}
