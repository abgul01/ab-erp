<?php

namespace App\Http\Controllers\Api\Npd;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\npd_deliverable;
use App\Models\npd_milestone;
use App\Models\npd_project;
use App\Models\npd_project_phase;
use App\Models\npd_task;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Task, milestone, dan deliverable per fase.
 *
 * PRD_Modul_NPD_FTPI.md §7.2, §7.3
 */
class NpdTaskController extends Controller
{
    /** Task milik seorang pengguna di seluruh proyek — untuk halaman "tugas saya". */
    public function myTasks(Request $request)
    {
        $rows = npd_task::query()
            ->join('npd_project_phase as p', 'p.id', '=', 'npd_task.main_id')
            ->join('npd_project as n', 'n.id', '=', 'p.main_id')
            ->where('npd_task.assigned_to', $request->user()->id)
            ->whereIn('n.status', ['RUNNING', 'ON_HOLD'])
            ->when(! $request->boolean('all'), fn ($q) => $q->where('npd_task.status', '<>', 'DONE'))
            ->orderBy('npd_task.planned_end')
            ->get([
                'npd_task.*', 'n.code as project_code', 'n.name as project_name', 'p.phase_no',
            ]);

        return ApiResponse::collection($rows->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'project_code' => $t->project_code,
            'project_name' => $t->project_name,
            'phase_no' => (int) $t->phase_no,
            'planned_end' => $t->planned_end,
            'progress_pct' => (int) $t->progress_pct,
            'status' => $t->status,
            'late' => $t->status !== 'DONE' && $t->planned_end
                && Carbon::parse($t->planned_end)->isBefore(now()->startOfDay()),
        ]));
    }

    public function storeTask(Request $request, int $phaseId)
    {
        $phase = $this->openPhase($phaseId);
        $data = $this->validateTask($request);

        $task = $phase->tasks()->create($data);
        AuditLogger::record($request, "Tambah task NPD #{$task->id} pada fase {$phase->phase_no}");

        return ApiResponse::item($task->load('assignee'), 201);
    }

    public function updateTask(Request $request, int $id)
    {
        $task = npd_task::findOrFail($id);
        $this->openPhase($task->main_id);

        $data = $this->validateTask($request);

        /*
         * Task hanya boleh ditandai selesai kalau pendahulunya sudah selesai.
         * Urutan yang dicatat tapi tidak ditegakkan hanya menjadi hiasan pada
         * bagan, dan gate akan lolos di atas pekerjaan yang belum dikerjakan.
         */
        if (($data['status'] ?? null) === 'DONE' && $task->predecessor_id) {
            $pred = npd_task::find($task->predecessor_id);
            if ($pred && $pred->status !== 'DONE') {
                throw BizException::make(
                    'NPD_TASK_PRED',
                    "Task pendahulunya (\"{$pred->name}\") belum selesai."
                );
            }
        }

        // Progres dan status dijaga tetap sejalan: yang selesai itu 100 persen.
        if (($data['status'] ?? null) === 'DONE') {
            $data['progress_pct'] = 100;
            $data['actual_end'] ??= now()->toDateString();
        }

        $task->update($data);

        return ApiResponse::item($task->fresh()->load('assignee'));
    }

    public function destroyTask(Request $request, int $id)
    {
        $task = npd_task::findOrFail($id);
        $this->openPhase($task->main_id);

        // Task lain bisa menunjuk task ini sebagai pendahulu; tautannya dilepas
        // agar tidak menyisakan penunjuk ke sesuatu yang tak ada.
        npd_task::where('predecessor_id', $task->id)->update(['predecessor_id' => null]);
        $task->delete();

        AuditLogger::record($request, "Hapus task NPD #{$id}");

        return ApiResponse::item(['message' => 'Task dihapus.']);
    }

    /* ---------------- deliverable ---------------- */

    /**
     * Perbarui status deliverable.
     *
     * WAIVED wajib beralasan: mengecualikan PFMEA tanpa menuliskan mengapa
     * adalah persis yang ditanyakan auditor pelanggan setahun kemudian.
     */
    public function updateDeliverable(Request $request, int $id)
    {
        $del = npd_deliverable::findOrFail($id);
        $this->openPhase($del->main_id);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'status' => ['required', 'in:OPEN,IN_PROGRESS,DONE,WAIVED'],
            'resp_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'doc_id' => ['nullable', 'integer', 'exists:npd_doc,id'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        if ($data['status'] === 'WAIVED' && blank($data['note'] ?? null)) {
            throw BizException::make(
                'NPD_WAIVE_REASON',
                'Deliverable yang dikecualikan harus disertai alasan.'
            );
        }

        if ($data['status'] === 'DONE') {
            $data['submitted_date'] = now()->toDateString();
        }

        $del->update($data);
        AuditLogger::record($request, "Deliverable NPD #{$id} → {$data['status']}");

        return ApiResponse::item($del->fresh()->load(['std', 'responsible', 'doc']));
    }

    /* ---------------- milestone ---------------- */

    public function storeMilestone(Request $request, int $projectId)
    {
        $project = npd_project::findOrFail($projectId);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'planned_date' => ['required', 'date'],
        ]);

        $ms = $project->milestones()->create($data + ['status' => 'PLANNED']);

        return ApiResponse::item($ms, 201);
    }

    public function updateMilestone(Request $request, int $id)
    {
        $ms = npd_milestone::findOrFail($id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'planned_date' => ['required', 'date'],
            'actual_date' => ['nullable', 'date'],
        ]);

        // Status milestone diturunkan dari tanggalnya, bukan diketik terpisah —
        // dua sumber kebenaran untuk hal yang sama pasti berbeda suatu hari.
        $data['status'] = $data['actual_date']
            ? 'DONE'
            : (Carbon::parse($data['planned_date'])->isBefore(now()->startOfDay()) ? 'LATE' : 'PLANNED');

        $ms->update($data);

        return ApiResponse::item($ms->fresh());
    }

    public function destroyMilestone(int $id)
    {
        npd_milestone::findOrFail($id)->delete();

        return ApiResponse::item(['message' => 'Milestone dihapus.']);
    }

    /* ---------------- helpers ---------------- */

    /** Fase yang gate-nya sudah disetujui tidak boleh diubah isinya lagi. */
    private function openPhase(int $phaseId): npd_project_phase
    {
        $phase = npd_project_phase::findOrFail($phaseId);

        if (in_array($phase->status, ['SUBMITTED', 'APPROVED'], true)) {
            throw BizException::make(
                'NPD_PHASE_LOCKED',
                'Fase yang sudah diajukan atau disetujui gate-nya tidak dapat diubah.'
            );
        }

        return $phase;
    }

    private function validateTask(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'descrip' => ['nullable', 'string', 'max:300'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'planned_start' => ['nullable', 'date'],
            'planned_end' => ['nullable', 'date'],
            'actual_start' => ['nullable', 'date'],
            'actual_end' => ['nullable', 'date'],
            'progress_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
            'predecessor_id' => ['nullable', 'integer', 'exists:npd_task,id'],
            'status' => ['nullable', 'in:OPEN,RUNNING,DONE,CANCELLED'],
        ]);
    }
}
