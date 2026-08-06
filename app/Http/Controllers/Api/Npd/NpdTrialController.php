<?php

namespace App\Http\Controllers\Api\Npd;

use App\Http\Controllers\Controller;
use App\Models\npd_project;
use App\Models\npd_trial_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NpdTrialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Trial produksi dan hasil ukurnya.
 *
 * PRD_Modul_NPD_FTPI.md §7.5
 */
class NpdTrialController extends Controller
{
    private array $with = ['detail.param', 'detail.inspector', 'wo', 'machine'];

    public function __construct(private NpdTrialService $svc) {}

    /** Trial satu proyek, beserta parameter inspeksi yang berlaku untuk part-nya. */
    public function index(Request $request, int $projectId)
    {
        $project = npd_project::with('item')->findOrFail($projectId);

        $trials = npd_trial_main::with($this->with)
            ->where('main_id', $projectId)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($t) => $t->toArray() + ['pass_rate' => $t->passRate()]);

        return ApiResponse::item([
            'project' => $project,
            'trials' => $trials,
            /*
             * Parameter yang tersedia, lengkap dengan spesifikasi yang berlaku
             * untuk part ini bila sudah terdaftar — supaya penguji tidak
             * mengetik ulang toleransi yang sudah ada di master.
             */
            'params' => DB::table('m_inspection_param')
                ->where('active', 1)->orderBy('code')
                ->get(['id', 'code', 'name', 'uom', 'method'])
                ->map(function ($p) use ($project) {
                    $spec = $project->item_id
                        ? $this->svc->specFor((int) $project->item_id, (int) $p->id)
                        : ['nominal' => null, 'min_value' => null, 'max_value' => null, 'source' => 'MANUAL'];

                    return (array) $p + $spec;
                }),
        ]);
    }

    public function show(int $id)
    {
        $trial = npd_trial_main::with($this->with)->findOrFail($id);

        return ApiResponse::item($trial->toArray() + ['pass_rate' => $trial->passRate()]);
    }

    /** Membuat trial sekaligus Work Order uji cobanya. */
    public function store(Request $request, int $projectId)
    {
        $project = npd_project::findOrFail($projectId);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'trial_type' => ['nullable', 'in:'.implode(',', npd_trial_main::TYPES)],
            'planned_qty' => ['required', 'integer', 'min:1'],
            'machine_id' => ['nullable', 'integer', 'exists:m_machine,id'],
            'process_main_id' => ['nullable', 'integer', 'exists:m_process_main,id'],
            'phase_id' => ['nullable', 'integer', 'exists:npd_project_phase,id'],
        ]);

        $trial = $this->svc->create($project, $data, $request->user()->id);

        return ApiResponse::item($trial->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $trial = npd_trial_main::findOrFail($id);
        $this->svc->assertDraft($trial);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'trial_type' => ['nullable', 'in:'.implode(',', npd_trial_main::TYPES)],
            'planned_qty' => ['required', 'integer', 'min:1'],
            'machine_id' => ['nullable', 'integer', 'exists:m_machine,id'],
        ]);

        DB::transaction(function () use ($trial, $data) {
            $trial->update($data);
            // Qty Work Order ikut menyesuaikan: keduanya harus bicara tentang
            // jumlah yang sama.
            DB::table('prd_wo_main')->where('id', $trial->wo_id)
                ->update(['qty' => $data['planned_qty'], 'date' => $data['date'], 'updated_at' => now()]);
        });

        return ApiResponse::item($trial->fresh()->load($this->with));
    }

    /** Simpan hasil ukur; judgement dihitung server. */
    public function saveResults(Request $request, int $id)
    {
        $trial = npd_trial_main::findOrFail($id);

        $data = $request->validate([
            'results' => ['required', 'array', 'min:1'],
            'results.*.param_id' => ['required', 'integer', 'exists:m_inspection_param,id'],
            'results.*.sample_no' => ['nullable', 'integer', 'min:1', 'max:999'],
            'results.*.nominal' => ['nullable', 'numeric'],
            'results.*.min_value' => ['nullable', 'numeric'],
            'results.*.max_value' => ['nullable', 'numeric'],
            'results.*.measured' => ['required', 'numeric'],
            'results.*.instrument' => ['nullable', 'string', 'max:50'],
            'results.*.inspector_id' => ['nullable', 'integer', 'exists:users,id'],
            'results.*.note' => ['nullable', 'string', 'max:150'],
        ]);

        $saved = $this->svc->saveResults($trial, $data['results'], $request->user()->id);
        AuditLogger::record($request, "Simpan hasil ukur trial {$trial->code}", $trial->code);

        return ApiResponse::item($saved->toArray() + ['pass_rate' => $saved->passRate()]);
    }

    public function finish(Request $request, int $id)
    {
        $trial = npd_trial_main::findOrFail($id);

        $data = $request->validate([
            'produced_qty' => ['required', 'integer', 'min:0'],
            'ok_qty' => ['required', 'integer', 'min:0'],
            'ng_qty' => ['required', 'integer', 'min:0'],
            'conclusion' => ['nullable', 'string', 'max:400'],
        ]);

        $done = $this->svc->finish($trial, $data, $request->user()->id);

        return ApiResponse::item($done->toArray() + ['pass_rate' => $done->passRate()]);
    }

    public function destroy(Request $request, int $id)
    {
        $trial = npd_trial_main::findOrFail($id);
        $this->svc->assertDraft($trial);

        DB::transaction(function () use ($trial, $request) {
            $trial->detail()->delete();
            // Work Order-nya ikut dibuang: ia dibuat khusus untuk trial ini dan
            // tidak punya arti sendiri.
            DB::table('prd_wo_main')->where('id', $trial->wo_id)->delete();
            $trial->delete();

            AuditLogger::record($request, "Hapus trial {$trial->code}", $trial->code);
        });

        return ApiResponse::item(['message' => 'Trial dihapus beserta Work Order uji cobanya.']);
    }
}
