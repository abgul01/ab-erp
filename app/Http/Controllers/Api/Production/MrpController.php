<?php

namespace App\Http\Controllers\Api\Production;

use App\Http\Controllers\Controller;
use App\Jobs\RunMrpJob;
use App\Models\prd_mrp_detail;
use App\Models\prd_mrp_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\MrpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Material Requirements Planning. A run explodes the approved MPP over the
 * requested periods and nets requirements time-phased:
 *   FG:  gross = MPP plan; net = gross − (FG stock + open WO) → suggest WO
 *   RM:  gross = Σ FG net × BOM length; net = gross − (RM stock + open PO)
 *        → suggest PR, with net_req_kg for the import-quota check.
 *
 * The actual explosion runs in a background queue job (RunMrpJob) to avoid
 * timeout for large BOM sets. The run() method creates a placeholder MRP
 * record (status=PROCESSING), dispatches the job, and returns immediately.
 * The frontend polls until status flips to DONE.
 */
class MrpController extends Controller
{
    public function index(Request $request)
    {
        $rows = prd_mrp_main::query()->withCount('detail')->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        $main = prd_mrp_main::with(['detail.item'])->findOrFail($id);
        $detail = $main->detail->map(fn ($d) => [
            'item_id' => $d->item_id,
            'item_code' => $d->item?->code,
            'part_name' => $d->item?->part_name,
            'period' => $d->period,
            'level' => $d->suggestion === 'WO' ? 'FG' : 'RM',
            'gross_req' => (int) $d->gross_req,
            // Where the requirement came from, so a planner can weigh it: a firm
            // order and a forecast are not the same kind of number.
            'demand_src' => $d->demand_src,
            'demand_mpp' => (int) $d->demand_mpp,
            'demand_fc' => (int) $d->demand_fc,
            'demand_so' => (int) $d->demand_so,
            'onhand' => (int) $d->onhand,
            'open_po' => (int) $d->open_po,
            'open_wo' => (int) $d->open_wo,
            'net_req' => (int) $d->net_req,
            'net_req_kg' => $d->net_req_kg !== null ? (float) $d->net_req_kg : null,
            'suggestion' => $d->suggestion,
        ]);

        return ApiResponse::item(['id' => $main->id, 'run_date' => $main->run_date, 'status' => $main->status, 'detail' => $detail->values()]);
    }

    public function run(Request $request)
    {
        $data = $request->validate([
            'periods' => ['required', 'array', 'min:1'],
            'periods.*' => ['string', 'regex:/^\d{6}$/'],
        ]);
        $periods = collect($data['periods'])->unique()->sort()->values()->all();

        // Create a placeholder so the frontend can track progress
        $main = prd_mrp_main::create([
            'run_date' => now(),
            'user_id' => $request->user()->id,
            'status' => 'PROCESSING',
            'created_at' => now(),
        ]);

        RunMrpJob::dispatch($periods, $main->id, (int) $request->user()->id);

        AuditLogger::record($request, 'Run MRP (bg) '.implode(',', $periods)." ({$main->id})");

        return ApiResponse::item(['id' => $main->id, 'status' => 'PROCESSING', 'message' => 'MRP sedang diproses. Silakan refresh halaman.']);
    }

    public function destroy(Request $request, int $id)
    {
        $main = prd_mrp_main::findOrFail($id);
        DB::transaction(function () use ($main, $request) {
            prd_mrp_detail::where('main_id', $main->id)->delete();
            $main->delete();
            AuditLogger::record($request, "Delete MRP run {$main->id}");
        });

        return ApiResponse::item(['message' => 'MRP run dihapus.']);
    }

    public function generatePr(Request $request, int $id)
    {
        $main = prd_mrp_main::findOrFail($id);

        if ($main->status !== 'DONE') {
            return response()->json(['errors' => [['code' => 'MRP_NOT_DONE', 'message' => 'MRP belum selesai diproses.', 'field' => null]]], 422);
        }

        try {
            $result = app(MrpService::class)->generatePr($id, (int) $request->user()->id);
            AuditLogger::record($request, "Generate PR from MRP #{$id} → {$result['code']}");

            return ApiResponse::item($result, 201);
        } catch (\RuntimeException $e) {
            return response()->json(['errors' => [['code' => 'PR_GEN_FAILED', 'message' => $e->getMessage(), 'field' => null]]], 422);
        }
    }
}
