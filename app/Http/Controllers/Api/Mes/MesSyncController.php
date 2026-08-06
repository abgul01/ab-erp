<?php

namespace App\Http\Controllers\Api\Mes;

use App\Http\Controllers\Controller;
use App\Models\m_machine;
use App\Models\prd_wip;
use App\Models\prd_wo_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\MesSyncService;
use App\Support\PlanningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * MES offline terminal support.
 *
 * - snapshot: master data the terminal caches so it can keep scanning offline
 * - sync:     flush of the terminal's queued operations, deduped by client_uuid
 *
 * LLD §6.2, PRD §2.1
 */
class MesSyncController extends Controller
{
    public function __construct(private MesSyncService $svc) {}

    /** Flush the terminal's offline queue. Partial success is normal and reported per operation. */
    public function sync(Request $request)
    {
        $data = $request->validate([
            'operations' => ['required', 'array', 'max:500'],
            'operations.*.client_uuid' => ['required', 'string', 'max:36'],
            'operations.*.method' => ['required', 'string', 'in:POST,PUT,PATCH,DELETE'],
            'operations.*.url' => ['required', 'string', 'max:200'],
            'operations.*.data' => ['present', 'array'],
            'operations.*.client_at' => ['nullable', 'date'],
            'operations.*.type' => ['nullable', 'string', 'max:50'],
        ]);

        $result = $this->svc->processBatch($data['operations'], $request);

        AuditLogger::record(
            $request,
            "MES sync: {$result['processed']} terkirim, {$result['skipped']} duplikat, {$result['failed']} gagal"
        );

        return ApiResponse::item($result);
    }

    /**
     * Master data snapshot for offline work: the released Work Orders a terminal
     * may be asked to scan, plus the lookup tables the scan screens need.
     */
    public function snapshot(Request $request)
    {
        $planning = new PlanningService;

        $wos = prd_wo_main::with('fg')->where('status', 2)->orderBy('id')->limit(300)->get();
        $wipByWo = prd_wip::whereIn('wo_id', $wos->pluck('id'))->get()->keyBy('wo_id');

        return ApiResponse::item([
            'generated_at' => now()->toIso8601String(),
            'expires_in_hours' => MesSyncService::MAX_OFFLINE_HOURS,
            'work_orders' => $wos->map(fn ($wo) => [
                'id' => $wo->id,
                'code' => $wo->code,
                'fg_id' => $wo->fg_id,
                'fg_code' => $wo->fg?->code,
                'qty' => $wo->qty,
                'wip_code' => $wipByWo[$wo->id]->code ?? null,
                'no_dp' => $wipByWo[$wo->id]->no_dp ?? null,
                'routing' => $planning->routing((int) $wo->fg_id, $wo->process_main_id ? (int) $wo->process_main_id : null),
            ])->values(),
            'machines' => m_machine::orderBy('code')->get(['id', 'code', 'name']),
            'shifts' => DB::table('m_shift')->orderBy('id')->get(['id', 'name']),
            'dt_categories' => DB::table('tr_dt_category')->orderBy('id')->get(['id', 'name_c_dt as name']),
        ]);
    }

    /** Operations that were rejected on replay — shown on the terminal for manual fixing. */
    public function failed(Request $request)
    {
        return ApiResponse::collection($this->svc->failedOps());
    }

    /** Housekeeping on the operation log. */
    public function clear(Request $request)
    {
        $deleted = $this->svc->clearOld();
        AuditLogger::record($request, "MES oplog dibersihkan: {$deleted} baris");

        return ApiResponse::ok(['deleted' => $deleted]);
    }
}
