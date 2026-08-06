<?php

namespace App\Http\Controllers\Api\Npd;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\npd_cp_main;
use App\Models\npd_fmea_main;
use App\Models\npd_project;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NpdQualityService;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FMEA (DFMEA/PFMEA) dan Control Plan sebuah proyek NPD.
 *
 * PRD_Modul_NPD_FTPI.md §7.5 FR-16, FR-17
 */
class NpdQualityController extends Controller
{
    public function __construct(private NpdQualityService $svc) {}

    /** Seluruh FMEA dan control plan satu proyek, plus master yang dibutuhkan layarnya. */
    public function show(int $projectId)
    {
        $project = npd_project::with('item')->findOrFail($projectId);

        return ApiResponse::item([
            'project' => $project,
            'fmeas' => npd_fmea_main::with(['detail.proc', 'detail.responsible'])
                ->where('main_id', $projectId)->orderByDesc('id')->get()
                ->map(fn ($f) => $f->toArray() + ['above_threshold' => $f->aboveThreshold()->count()]),
            'control_plans' => npd_cp_main::with(['detail.proc', 'detail.param'])
                ->where('main_id', $projectId)->orderByDesc('id')->get(),
            'processes' => DB::table('m_process')->where('active', 1)->orderBy('code')->get(['id', 'code', 'name_p']),
            'params' => DB::table('m_inspection_param')->where('active', 1)->orderBy('code')
                ->get(['id', 'code', 'name', 'uom', 'method']),
        ]);
    }

    /* ---------------- FMEA ---------------- */

    public function storeFmea(Request $request, int $projectId)
    {
        npd_project::findOrFail($projectId);

        $data = $request->validate([
            'fmea_type' => ['required', 'in:'.implode(',', npd_fmea_main::TYPES)],
            'revision' => ['nullable', 'string', 'max:10'],
            'team' => ['nullable', 'string', 'max:200'],
            'date' => ['required', 'date'],
            'rpn_threshold' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $fmea = npd_fmea_main::create($data + [
            'main_id' => $projectId,
            'code' => app(NumberingService::class)->next('FMEA', $data['fmea_type'] === 'DESIGN' ? 'DFMEA' : 'PFMEA'),
            'status' => 'DRAFT',
            'user_id' => $request->user()->id,
        ]);

        AuditLogger::record($request, "Buat {$fmea->fmea_type} FMEA {$fmea->code}", $fmea->code);

        return ApiResponse::item($fmea->load('detail'), 201);
    }

    /** Simpan baris risiko; RPN dihitung server. */
    public function saveFmeaLines(Request $request, int $id)
    {
        $fmea = npd_fmea_main::findOrFail($id);

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.proc_id' => ['nullable', 'integer', 'exists:m_process,id'],
            'lines.*.item_function' => ['required', 'string', 'max:150'],
            'lines.*.failure_mode' => ['required', 'string', 'max:150'],
            'lines.*.effect' => ['required', 'string', 'max:200'],
            'lines.*.severity' => ['required', 'integer', 'min:1', 'max:10'],
            'lines.*.cause' => ['required', 'string', 'max:200'],
            'lines.*.occurrence' => ['required', 'integer', 'min:1', 'max:10'],
            'lines.*.current_control' => ['nullable', 'string', 'max:200'],
            'lines.*.detection' => ['required', 'integer', 'min:1', 'max:10'],
            'lines.*.recommended_action' => ['nullable', 'string', 'max:300'],
            'lines.*.action_taken' => ['nullable', 'string', 'max:300'],
            'lines.*.resp_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'lines.*.due_date' => ['nullable', 'date'],
            'lines.*.status' => ['nullable', 'in:OPEN,DONE'],
        ]);

        $saved = $this->svc->saveFmeaLines($fmea, $data['lines']);
        AuditLogger::record($request, "Simpan baris FMEA {$fmea->code}", $fmea->code);

        return ApiResponse::item($saved->toArray() + ['above_threshold' => $saved->aboveThreshold()->count()]);
    }

    public function finalizeFmea(Request $request, int $id)
    {
        $fmea = npd_fmea_main::findOrFail($id);

        return ApiResponse::item($this->svc->finalizeFmea($fmea));
    }

    public function destroyFmea(Request $request, int $id)
    {
        $fmea = npd_fmea_main::findOrFail($id);

        // Control plan bisa menunjuk baris FMEA ini sebagai asal-usulnya;
        // membuangnya akan menghapus jejak "kenapa ini diukur".
        $used = DB::table('npd_cp_det')
            ->whereIn('fmea_det_id', $fmea->detail()->pluck('id'))
            ->exists();

        if ($used) {
            throw BizException::make(
                'NPD_FMEA_USED',
                'FMEA ini dipakai sebagai dasar control plan. Hapus baris control plan-nya dulu.'
            );
        }

        DB::transaction(function () use ($fmea, $request) {
            $fmea->detail()->delete();
            $fmea->delete();
            AuditLogger::record($request, "Hapus FMEA {$fmea->code}", $fmea->code);
        });

        return ApiResponse::item(['message' => 'FMEA dihapus.']);
    }

    /* ---------------- Control Plan ---------------- */

    public function storeCp(Request $request, int $projectId)
    {
        npd_project::findOrFail($projectId);

        $data = $request->validate([
            'cp_type' => ['required', 'in:'.implode(',', npd_cp_main::TYPES)],
            'revision' => ['nullable', 'string', 'max:10'],
            'date' => ['required', 'date'],
        ]);

        $cp = npd_cp_main::create($data + [
            'main_id' => $projectId,
            'code' => app(NumberingService::class)->next('CP', 'CP'),
            'status' => 'DRAFT',
            'user_id' => $request->user()->id,
        ]);

        AuditLogger::record($request, "Buat control plan {$cp->code}", $cp->code);

        return ApiResponse::item($cp->load('detail'), 201);
    }

    public function saveCpLines(Request $request, int $id)
    {
        $cp = npd_cp_main::findOrFail($id);

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.seq' => ['nullable', 'integer', 'min:1'],
            'lines.*.proc_id' => ['nullable', 'integer', 'exists:m_process,id'],
            'lines.*.param_id' => ['nullable', 'integer', 'exists:m_inspection_param,id'],
            'lines.*.nominal' => ['nullable', 'numeric'],
            'lines.*.min_value' => ['nullable', 'numeric'],
            'lines.*.max_value' => ['nullable', 'numeric'],
            'lines.*.method' => ['nullable', 'string', 'max:100'],
            'lines.*.sample_size' => ['nullable', 'integer', 'min:1'],
            'lines.*.frequency' => ['nullable', 'string', 'max:60'],
            'lines.*.control_method' => ['nullable', 'string', 'max:150'],
            'lines.*.reaction_plan' => ['nullable', 'string', 'max:250'],
            'lines.*.fmea_det_id' => ['nullable', 'integer', 'exists:npd_fmea_det,id'],
            'lines.*.to_item_inspection' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($cp, $data) {
            $cp->detail()->delete();
            foreach ($data['lines'] as $i => $l) {
                $cp->detail()->create($l + ['seq' => $l['seq'] ?? $i + 1]);
            }
        });

        AuditLogger::record($request, "Simpan baris control plan {$cp->code}", $cp->code);

        return ApiResponse::item($cp->fresh()->load('detail.proc', 'detail.param'));
    }

    /** Susun baris kendali dari risiko PFMEA di atas ambang. */
    public function generateFromFmea(Request $request, int $id)
    {
        $cp = npd_cp_main::findOrFail($id);

        $data = $request->validate([
            'fmea_id' => ['required', 'integer', 'exists:npd_fmea_main,id'],
            'replace' => ['nullable', 'boolean'],
        ]);

        $fmea = npd_fmea_main::findOrFail($data['fmea_id']);
        $result = $this->svc->generateCpFromFmea($cp, $fmea, (bool) ($data['replace'] ?? false));

        return ApiResponse::item($result);
    }

    public function finalizeCp(Request $request, int $id)
    {
        $cp = npd_cp_main::findOrFail($id);

        return ApiResponse::item($this->svc->finalizeCp($cp));
    }

    public function destroyCp(Request $request, int $id)
    {
        $cp = npd_cp_main::findOrFail($id);

        DB::transaction(function () use ($cp, $request) {
            $cp->detail()->delete();
            $cp->delete();
            AuditLogger::record($request, "Hapus control plan {$cp->code}", $cp->code);
        });

        return ApiResponse::item(['message' => 'Control plan dihapus.']);
    }
}
