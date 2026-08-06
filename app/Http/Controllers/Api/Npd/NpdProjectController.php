<?php

namespace App\Http\Controllers\Api\Npd;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\npd_member;
use App\Models\npd_project;
use App\Models\npd_project_phase;
use App\Models\npd_rfq;
use App\Support\ApiResponse;
use App\Support\ApprovalEngine;
use App\Support\AuditLogger;
use App\Support\NpdHandoverService;
use App\Support\NpdService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Proyek NPD: header, fase, anggota tim, dan gate antar-fase.
 *
 * PRD_Modul_NPD_FTPI.md §7.2, §7.7
 */
class NpdProjectController extends Controller
{
    private array $with = [
        'cus', 'pm', 'item',
        'phases.phase', 'phases.deliverables.std', 'phases.tasks.assignee',
        'milestones', 'members.user', 'docs',
    ];

    public function __construct(private NpdService $svc) {}

    public function index(Request $request)
    {
        $rows = npd_project::with(['cus', 'pm'])
            ->withCount('phases')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$s}%")
                ->orWhere('name', 'like', "%{$s}%")
                ->orWhere('part_name', 'like', "%{$s}%")))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('phase_no'), fn ($q, $s) => $q->where('current_phase_no', $s))
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        return ApiResponse::item(npd_project::with($this->with)->findOrFail($id));
    }

    /** Ringkasan funnel per fase, proyek/task terlambat, gate menunggu. */
    public function dashboard()
    {
        return ApiResponse::item([
            'summary' => $this->svc->summary(),
            'lead_times' => $this->svc->leadTimes(),
        ]);
    }

    /** Laporan KPI: lead time, ketepatan target SOP, gate reject, PPAP, adopsi. */
    public function report()
    {
        return ApiResponse::item($this->svc->report());
    }

    /**
     * Proyek dibuat dari RFQ yang studi kelayakannya GO — bukan dari layar
     * kosong. Proyek tanpa asal-usul adalah proyek yang tidak ada yang memesan.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'rfq_id' => ['required', 'integer', 'exists:npd_rfq,id'],
            'name' => ['nullable', 'string', 'max:150'],
            'project_type' => ['nullable', 'in:'.implode(',', npd_project::TYPES)],
            'parent_item_id' => ['nullable', 'integer', 'exists:m_item,id'],
            'target_sop' => ['nullable', 'date'],
            'pm_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['nullable', 'in:LOW,NORMAL,HIGH'],
            'phase_plan' => ['array'],
        ]);

        $rfq = npd_rfq::findOrFail($data['rfq_id']);
        $project = $this->svc->createFromRfq($rfq, $data, $request->user()->id);

        AuditLogger::record($request, "Buat proyek NPD {$project->code}", $project->code);

        return ApiResponse::item($project->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $project = npd_project::findOrFail($id);
        $this->assertOpen($project);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'part_name' => ['required', 'string', 'max:100'],
            'drawing_no' => ['nullable', 'string', 'max:50'],
            'project_type' => ['required', 'in:'.implode(',', npd_project::TYPES)],
            'parent_item_id' => ['nullable', 'integer', 'exists:m_item,id'],
            'target_sop' => ['nullable', 'date'],
            'pm_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['nullable', 'in:LOW,NORMAL,HIGH'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $project->update($data);
        AuditLogger::record($request, "Ubah proyek NPD {$project->code}", $project->code);

        return ApiResponse::item($project->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $project = npd_project::findOrFail($id);

        // Proyek yang sudah lewat fase pertama meninggalkan jejak approval dan
        // dokumen; membuangnya berarti membuang riwayat itu juga.
        if ($project->current_phase_no > 1 || $project->status !== 'RUNNING') {
            throw BizException::make(
                'NPD_DELETE',
                'Proyek yang sudah berjalan tidak dapat dihapus — batalkan saja agar riwayatnya tetap ada.'
            );
        }

        DB::transaction(function () use ($project, $request) {
            $phaseIds = $project->phases()->pluck('id');
            DB::table('npd_deliverable')->whereIn('main_id', $phaseIds)->delete();
            DB::table('npd_task')->whereIn('main_id', $phaseIds)->delete();
            $project->phases()->delete();
            $project->milestones()->delete();
            $project->members()->delete();
            DB::table('npd_rfq')->where('main_id', $project->id)->update(['main_id' => null, 'status' => 'OPEN']);
            $project->delete();

            AuditLogger::record($request, "Hapus proyek NPD {$project->code}", $project->code);
        });

        return ApiResponse::item(['message' => 'Proyek NPD dihapus.']);
    }

    /** Tahan sementara, lalu jalankan lagi — tanpa kehilangan posisi fase. */
    public function hold(Request $request, int $id)
    {
        $project = npd_project::findOrFail($id);

        if (! in_array($project->status, ['RUNNING', 'ON_HOLD'], true)) {
            throw BizException::make('NPD_STATE', 'Hanya proyek berjalan yang dapat ditahan atau dilanjutkan.');
        }

        $to = $project->status === 'RUNNING' ? 'ON_HOLD' : 'RUNNING';
        $project->update(['status' => $to]);
        AuditLogger::record($request, "Proyek NPD {$project->code} → {$to}", $project->code);

        return ApiResponse::item($project->load($this->with));
    }

    public function cancel(Request $request, int $id)
    {
        $project = npd_project::findOrFail($id);
        $request->validate(['note' => ['required', 'string', 'max:300']]);

        if (in_array($project->status, ['CLOSED', 'CANCELLED'], true)) {
            throw BizException::make('NPD_STATE', 'Proyek ini sudah selesai atau sudah dibatalkan.');
        }

        $project->update(['status' => 'CANCELLED', 'note' => $request->input('note')]);
        AuditLogger::record($request, "Batalkan proyek NPD {$project->code}: ".$request->input('note'), $project->code);

        return ApiResponse::item($project->load($this->with));
    }

    /* ---------------- gate ---------------- */

    /** Ajukan gate fase berjalan. Deliverable wajib diperiksa di service. */
    public function submitGate(Request $request, int $id, int $phaseNo)
    {
        $phase = $this->phaseOf($id, $phaseNo);
        $this->svc->submitGate($phase);

        return ApiResponse::item(npd_project::with($this->with)->find($id));
    }

    public function approveGate(Request $request, int $id, int $phaseNo)
    {
        $phase = $this->phaseOf($id, $phaseNo);

        if ($phase->status !== 'SUBMITTED') {
            throw BizException::make('NPD_GATE_STATE', 'Gate ini tidak sedang menunggu persetujuan.');
        }

        app(ApprovalEngine::class)->approve($phase, $request->user(), $request->input('note'));

        return ApiResponse::item(npd_project::with($this->with)->find($id));
    }

    public function rejectGate(Request $request, int $id, int $phaseNo)
    {
        $request->validate(['note' => ['required', 'string', 'max:300']]);
        $phase = $this->phaseOf($id, $phaseNo);

        app(ApprovalEngine::class)->reject($phase, $request->user(), $request->input('note'));

        return ApiResponse::item(npd_project::with($this->with)->find($id));
    }

    /**
     * Pratinjau serah terima: apa yang akan dibuat, dan apa yang masih menahan.
     *
     * Dibaca layar SPV sebelum tombolnya ditekan — keputusan diambil sambil
     * melihat isinya, bukan setelahnya.
     */
    public function handoverPreview(int $id)
    {
        $project = npd_project::with('item')->findOrFail($id);

        return ApiResponse::item(app(NpdHandoverService::class)->preview($project));
    }

    /**
     * Serah terima ke produksi — kewenangan SPV.
     *
     * Satu transaksi: BOM produksi, routing, cycle time, parameter inspeksi,
     * lalu part naik dari TRIAL ke MASSPRO. Sebelum ini part ditolak rencana
     * bulanan, forecast, sales order, dan Work Order produksi; sesudahnya
     * diterima semuanya.
     */
    public function handover(Request $request, int $id)
    {
        $project = npd_project::with('item')->findOrFail($id);

        $data = $request->validate([
            'cycle_times' => ['array'],
            'cycle_times.*.proc_id' => ['required', 'integer', 'exists:m_process,id'],
            'cycle_times.*.cycle_sec' => ['required', 'numeric', 'min:0'],
            'cycle_times.*.machine_id' => ['nullable', 'integer', 'exists:m_machine,id'],
            'cycle_times.*.setup_min' => ['nullable', 'numeric', 'min:0'],
        ]);

        $result = app(NpdHandoverService::class)->execute($project, $data, $request->user()->id);

        return ApiResponse::item($result);
    }

    /* ---------------- anggota tim ---------------- */

    public function saveMembers(Request $request, int $id)
    {
        $project = npd_project::findOrFail($id);
        $data = $request->validate([
            'members' => ['array'],
            'members.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'members.*.role' => ['required', 'in:'.implode(',', npd_member::ROLES)],
        ]);

        DB::transaction(function () use ($project, $data, $request) {
            $project->members()->delete();
            foreach ($data['members'] ?? [] as $m) {
                $project->members()->create($m);
            }
            AuditLogger::record($request, "Ubah tim proyek NPD {$project->code}", $project->code);
        });

        return ApiResponse::item($project->load($this->with));
    }

    /* ---------------- helpers ---------------- */

    private function assertOpen(npd_project $project): void
    {
        if (in_array($project->status, ['CLOSED', 'CANCELLED'], true)) {
            throw BizException::make('NPD_LOCKED', 'Proyek yang sudah ditutup atau dibatalkan tidak dapat diubah.');
        }
    }

    private function phaseOf(int $projectId, int $phaseNo): npd_project_phase
    {
        return npd_project_phase::where('main_id', $projectId)
            ->where('phase_no', $phaseNo)
            ->firstOr(fn () => throw BizException::make('NPD_PHASE_404', 'Fase tidak ditemukan pada proyek ini.'));
    }
}
