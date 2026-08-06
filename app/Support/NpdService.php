<?php

namespace App\Support;

use App\Exceptions\BizException;
use App\Models\npd_deliverable_std;
use App\Models\npd_feasibility;
use App\Models\npd_phase;
use App\Models\npd_project;
use App\Models\npd_project_phase;
use App\Models\npd_rfq;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aturan main proyek NPD: dari RFQ menjadi proyek, lalu melewati lima gate.
 *
 * Seluruh perpindahan fase ada di kelas ini, bukan tersebar di controller dan
 * model. Sebuah proyek hanya boleh maju lewat satu pintu — kalau pintunya ada
 * tiga, cepat atau lambat ketiganya akan berbeda aturan.
 *
 * PRD_Modul_NPD_FTPI.md §6, §7.2, §7.7
 */
class NpdService
{
    /**
     * Lahirkan proyek dari RFQ yang dinyatakan layak.
     *
     * Hanya kesimpulan GO yang membuka jalan. CONDITIONAL sengaja ditolak:
     * "layak dengan syarat" berarti syaratnya belum dipenuhi, dan proyek yang
     * dimulai di atas syarat yang belum jelas adalah proyek yang akan berhenti
     * di gate pertama.
     */
    public function createFromRfq(npd_rfq $rfq, array $data, int $userId): npd_project
    {
        $feas = npd_feasibility::where('rfq_id', $rfq->id)->latest('id')->first();

        if (! $feas || $feas->conclusion !== 'GO') {
            throw BizException::make(
                'NPD_NOT_FEASIBLE',
                'RFQ ini belum punya studi kelayakan dengan kesimpulan GO.'
            );
        }

        if ($rfq->main_id) {
            throw BizException::make('NPD_RFQ_USED', 'RFQ ini sudah menjadi proyek.');
        }

        return DB::transaction(function () use ($rfq, $feas, $data, $userId) {
            $project = npd_project::create([
                'code' => app(NumberingService::class)->next('NPD', 'NPD'),
                'name' => $data['name'] ?? $rfq->part_name,
                'cus_id' => $rfq->cus_id,
                'parent_item_id' => $data['parent_item_id'] ?? null,
                'part_name' => $rfq->part_name,
                'drawing_no' => $rfq->drawing_ref,
                'project_type' => $data['project_type'] ?? 'NEW',
                'target_sop' => $data['target_sop'] ?? null,
                'pm_user_id' => $data['pm_user_id'] ?? $userId,
                'priority' => $data['priority'] ?? 'NORMAL',
                'status' => 'RUNNING',
                'current_phase_no' => 1,
                'user_id' => $userId,
            ]);

            $this->instantiatePhases($project, $data['phase_plan'] ?? []);

            $rfq->update(['main_id' => $project->id, 'status' => 'QUOTED']);
            $feas->update(['main_id' => $project->id]);

            return $project;
        });
    }

    /**
     * Buat lima baris fase beserta deliverable wajibnya.
     *
     * Deliverable disalin dari master saat proyek dibuat, bukan dibaca langsung
     * dari master saat gate diperiksa: master boleh berubah tahun depan, dan
     * proyek yang sedang berjalan tidak boleh tiba-tiba kekurangan syarat yang
     * belum ada ketika ia dimulai.
     *
     * @param  array<int, array{planned_start?:string, planned_end?:string, pic_user_id?:int}>  $plan  indeks = nomor fase
     */
    public function instantiatePhases(npd_project $project, array $plan = []): void
    {
        $standards = npd_deliverable_std::all()->groupBy('phase_id');

        foreach (npd_phase::orderBy('phase_no')->get() as $phase) {
            $p = $plan[$phase->phase_no] ?? [];

            $projectPhase = npd_project_phase::create([
                'main_id' => $project->id,
                'phase_id' => $phase->id,
                'phase_no' => $phase->phase_no,
                'planned_start' => $p['planned_start'] ?? null,
                'planned_end' => $p['planned_end'] ?? null,
                'pic_user_id' => $p['pic_user_id'] ?? $project->pm_user_id,
                // Fase pertama langsung berjalan; sisanya menunggu gilirannya.
                'status' => $phase->phase_no === 1 ? 'RUNNING' : 'PLANNED',
                'actual_start' => $phase->phase_no === 1 ? now()->toDateString() : null,
            ]);

            foreach ($standards[$phase->id] ?? [] as $std) {
                $projectPhase->deliverables()->create([
                    'std_id' => $std->id,
                    'title' => $std->name,
                    'status' => 'OPEN',
                    'resp_user_id' => $projectPhase->pic_user_id,
                ]);
            }
        }
    }

    /**
     * Ajukan gate sebuah fase.
     *
     * Gate bukan sekadar tombol "lanjut": fase hanya boleh diajukan kalau
     * seluruh deliverable wajibnya sudah selesai atau dikecualikan dengan
     * alasan. Tanpa pemeriksaan ini, "sudah lewat gate" tidak berarti apa-apa
     * saat auditor pelanggan bertanya mana PFMEA-nya.
     */
    public function submitGate(npd_project_phase $phase): npd_project_phase
    {
        if ($phase->status !== 'RUNNING') {
            throw BizException::make(
                'NPD_PHASE_STATE',
                'Hanya fase yang sedang berjalan yang dapat diajukan gate-nya.'
            );
        }

        $pending = $phase->deliverables()
            ->whereHas('std', fn ($q) => $q->where('mandatory', 1))
            ->whereNotIn('status', ['DONE', 'WAIVED'])
            ->pluck('title');

        if ($pending->isNotEmpty()) {
            throw BizException::make(
                'NPD_GATE_INCOMPLETE',
                'Deliverable wajib berikut belum selesai: '.$pending->implode(', ')
                .'. Selesaikan atau kecualikan dengan alasan sebelum mengajukan gate.'
            );
        }

        $phase->submitForApproval();
        AuditLogger::record(request(), "Ajukan gate fase {$phase->phase_no} proyek #{$phase->main_id}");

        return $phase->fresh();
    }

    /**
     * Setelah gate disetujui penuh: buka fase berikutnya, atau tutup proyek.
     *
     * Dipanggil dari hook approval, jadi berlaku sama baik gate disetujui lewat
     * layar NPD maupun lewat inbox approval bersama.
     */
    public function advanceAfterGate(npd_project_phase $phase): void
    {
        $project = npd_project::find($phase->main_id);

        if (! $project) {
            return;
        }

        $next = npd_project_phase::where('main_id', $project->id)
            ->where('phase_no', $phase->phase_no + 1)
            ->first();

        if (! $next) {
            // Gate terakhir: proyek siap diserahterimakan ke produksi. Statusnya
            // bukan CLOSED — handover-nya sendiri belum terjadi.
            $project->update(['status' => 'HANDOVER']);

            return;
        }

        $next->update(['status' => 'RUNNING', 'actual_start' => now()->toDateString()]);
        $project->update(['current_phase_no' => $next->phase_no]);
    }

    /**
     * Ringkasan yang dibaca layar daftar dan dashboard.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $byPhase = DB::table('npd_project')
            ->whereIn('status', ['RUNNING', 'ON_HOLD'])
            ->groupBy('current_phase_no')
            ->selectRaw('current_phase_no, COUNT(*) as c')
            ->pluck('c', 'current_phase_no');

        $lateTasks = DB::table('npd_task as t')
            ->join('npd_project_phase as p', 'p.id', '=', 't.main_id')
            ->join('npd_project as n', 'n.id', '=', 'p.main_id')
            ->whereIn('n.status', ['RUNNING', 'ON_HOLD'])
            ->where('t.status', '<>', 'DONE')
            ->whereNotNull('t.planned_end')
            ->whereDate('t.planned_end', '<', now()->toDateString())
            ->count();

        $lateProjects = DB::table('npd_project')
            ->whereIn('status', ['RUNNING', 'ON_HOLD'])
            ->whereNotNull('target_sop')
            ->whereDate('target_sop', '<', now()->toDateString())
            ->count();

        $pendingGates = DB::table('approvals')
            ->where('doc_type', 'npd_project_phase')
            ->where('status', 'PENDING')
            ->distinct()->count('doc_id');

        return [
            'as_of' => now()->toDateString(),
            'running' => (int) DB::table('npd_project')->where('status', 'RUNNING')->count(),
            'on_hold' => (int) DB::table('npd_project')->where('status', 'ON_HOLD')->count(),
            'handover' => (int) DB::table('npd_project')->where('status', 'HANDOVER')->count(),
            'closed' => (int) DB::table('npd_project')->where('status', 'CLOSED')->count(),
            'funnel' => collect(range(1, 5))->mapWithKeys(fn ($n) => [$n => (int) ($byPhase[$n] ?? 0)])->all(),
            'late_projects' => $lateProjects,
            'late_tasks' => $lateTasks,
            'pending_gates' => $pendingGates,
        ];
    }

    /**
     * KPI proyek pengembangan (PRD §11).
     *
     * Rata-rata lead time dihitung dari proyek yang **sudah** diserahterimakan
     * saja — memasukkan yang masih berjalan akan menurunkan angkanya secara
     * palsu, karena proyek yang baru dimulai kemarin ikut menyumbang satu hari.
     * Tetapi jumlah yang masih berjalan tetap dilaporkan, supaya terlihat
     * berapa banyak yang belum ikut dihitung.
     *
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $rows = collect($this->leadTimes());
        $done = $rows->where('ongoing', false);

        $onTime = $done->filter(function ($r) {
            $project = DB::table('npd_project')->where('id', $r['id'])->first(['target_sop', 'handover_date']);

            return $project?->target_sop && $project?->handover_date
                && Carbon::parse($project->handover_date)->lte(Carbon::parse($project->target_sop));
        });

        $withTarget = $done->filter(fn ($r) => DB::table('npd_project')->where('id', $r['id'])->whereNotNull('target_sop')->exists());

        // Gate yang pernah ditolak, beserta alasannya — bahan perbaikan proses,
        // dan satu-satunya KPI yang menunjuk ke penyebab, bukan ke akibat.
        $rejects = DB::table('approvals as a')
            ->join('npd_project_phase as ph', 'ph.id', '=', 'a.doc_id')
            ->join('npd_project as n', 'n.id', '=', 'ph.main_id')
            ->where('a.doc_type', 'npd_project_phase')
            ->where('a.status', 'REJECTED')
            ->orderByDesc('a.acted_at')
            ->limit(20)
            ->get(['n.code', 'n.name', 'ph.phase_no', 'a.note', 'a.acted_at']);

        // Seberapa lengkap PPAP saat dikirim: elemen wajib yang sempat kosong
        // adalah alasan paling sering submission dikembalikan pelanggan.
        $ppap = DB::table('npd_ppap_main')
            ->selectRaw("
                COUNT(*) as total,
                SUM(status = 'APPROVED') as approved,
                SUM(status = 'REJECTED') as rejected,
                SUM(status = 'INTERIM') as interim
            ")->first();

        // Berapa banyak part baru yang benar-benar lahir lewat modul ini.
        $viaNpd = (int) DB::table('npd_project')->whereNotNull('item_id')->whereNotNull('handover_date')->count();
        $trial = (int) DB::table('m_item')->where('lifecycle', ItemLifecycle::TRIAL)->count();

        return [
            'as_of' => now()->toDateString(),
            'lead_time' => [
                'avg_days' => $done->isEmpty() ? null : (int) round($done->avg('days')),
                'min_days' => $done->isEmpty() ? null : (int) $done->min('days'),
                'max_days' => $done->isEmpty() ? null : (int) $done->max('days'),
                'completed' => $done->count(),
                'ongoing' => $rows->where('ongoing', true)->count(),
            ],
            'on_time' => [
                'with_target' => $withTarget->count(),
                'on_time' => $onTime->count(),
                'pct' => $withTarget->isEmpty() ? null : round($onTime->count() / $withTarget->count() * 100, 1),
            ],
            'gate_rejects' => [
                'total' => (int) DB::table('approvals')->where('doc_type', 'npd_project_phase')->where('status', 'REJECTED')->count(),
                'recent' => $rejects,
            ],
            'ppap' => [
                'total' => (int) ($ppap->total ?? 0),
                'approved' => (int) ($ppap->approved ?? 0),
                'interim' => (int) ($ppap->interim ?? 0),
                'rejected' => (int) ($ppap->rejected ?? 0),
            ],
            'adoption' => [
                'handed_over' => $viaNpd,
                'still_trial' => $trial,
            ],
            'projects' => $rows->values(),
        ];
    }

    /**
     * Lead time: dari RFQ diterima sampai gate terakhir disetujui.
     *
     * Proyek yang belum selesai tetap dihitung sampai hari ini, dan ditandai —
     * rata-rata yang hanya memuat proyek yang sudah selesai selalu terlihat
     * lebih bagus daripada kenyataannya.
     */
    public function leadTimes(): array
    {
        $rows = DB::table('npd_project as n')
            ->leftJoin('npd_rfq as r', 'r.main_id', '=', 'n.id')
            ->leftJoin('m_contacts as c', 'c.id', '=', 'n.cus_id')
            ->selectRaw('n.id, n.code, n.name, n.status, n.current_phase_no, n.target_sop,
                c.company_n as customer, MIN(r.date) as rfq_date, n.handover_date, n.created_at')
            ->groupBy('n.id', 'n.code', 'n.name', 'n.status', 'n.current_phase_no',
                'n.target_sop', 'c.company_n', 'n.handover_date', 'n.created_at')
            ->orderByDesc('n.id')
            ->get();

        return $rows->map(function ($r) {
            $start = $r->rfq_date ?: $r->created_at;
            $end = $r->handover_date ?: now()->toDateString();

            return [
                'id' => (int) $r->id,
                'code' => $r->code,
                'name' => $r->name,
                'customer' => $r->customer,
                'status' => $r->status,
                'phase_no' => (int) $r->current_phase_no,
                'rfq_date' => $r->rfq_date,
                'handover_date' => $r->handover_date,
                'days' => (int) abs(Carbon::parse($start)->diffInDays($end)),
                'ongoing' => ! $r->handover_date,
                'late' => $r->target_sop && ! $r->handover_date
                    && Carbon::parse($r->target_sop)->isBefore(now()->startOfDay()),
            ];
        })->all();
    }
}
