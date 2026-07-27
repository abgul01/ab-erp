<?php

namespace App\Http\Controllers\Api\Production;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\prd_mps;
use App\Models\prd_mps_resched;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Maker-checker for moving a locked (APPROVED) MPS lot.
 *   store   — a planner (perm:mps,edit) requests a new date/machine.
 *   approve — an approver (perm:mps-approvals,edit) applies the move.
 *   reject  — an approver declines it.
 *   cancel  — the requester withdraws their own pending request.
 * A lot may only have one PENDING request at a time (so the board blink is
 * unambiguous). DRAFT lots are still moved directly via MpsController::update.
 */
class MpsRescheduleController extends Controller
{
    private array $with = ['mps.item', 'mps.machine', 'fromMachine', 'toMachine', 'requester', 'approver'];

    public function index(Request $request)
    {
        $query = prd_mps_resched::with($this->with);
        $query->where('status', $request->query('status', 'PENDING'));
        if ($mpsId = $request->query('mps_id')) {
            $query->where('mps_id', $mpsId);
        }
        $query->orderByDesc('id');

        return ApiResponse::paginated($query->paginate(min(max((int) $request->query('per_page', 50), 1), 500)));
    }

    /** Planner submits a reschedule request for an approved lot. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'mps_id' => ['required', 'integer', 'exists:prd_mps,id'],
            'to_date' => ['required', 'date'],
            'to_machine_id' => ['nullable', 'integer', 'exists:m_machine,id'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $mps = prd_mps::findOrFail($data['mps_id']);
        if ($mps->status !== 'APPROVED') {
            throw BizException::make('RESCHED_STATE', 'Hanya lot terkunci (APPROVED) yang perlu diajukan reschedule. Lot DRAFT bisa digeser langsung.');
        }
        if (prd_mps_resched::where('mps_id', $mps->id)->where('status', 'PENDING')->exists()) {
            throw BizException::make('RESCHED_DUP', 'Sudah ada permintaan reschedule yang menunggu persetujuan untuk lot ini.');
        }

        $req = prd_mps_resched::create([
            'mps_id' => $mps->id,
            'from_date' => substr((string) $mps->plan_date, 0, 10),
            'from_machine_id' => $mps->machine_id,
            'to_date' => $data['to_date'],
            'to_machine_id' => $data['to_machine_id'] ?? $mps->machine_id,
            'reason' => $data['reason'] ?? null,
            'status' => 'PENDING',
            'requested_by' => $request->user()->id,
        ]);
        AuditLogger::record($request, "Request reschedule MPS #{$mps->id} → {$req->to_date}");

        return ApiResponse::item($req->load($this->with), 201);
    }

    public function approve(Request $request, int $id)
    {
        $req = prd_mps_resched::findOrFail($id);
        $this->assertPending($req);

        DB::transaction(function () use ($req, $request) {
            $mps = prd_mps::lockForUpdate()->findOrFail($req->mps_id);
            $mps->update(['plan_date' => $req->to_date, 'machine_id' => $req->to_machine_id]);
            $req->update([
                'status' => 'APPROVED',
                'approved_by' => $request->user()->id,
                'decided_at' => now(),
                'decision_note' => $request->input('note'),
            ]);
            AuditLogger::record($request, "Approve reschedule MPS #{$req->mps_id} → {$req->to_date}");
        });

        return ApiResponse::item($req->load($this->with));
    }

    public function reject(Request $request, int $id)
    {
        $req = prd_mps_resched::findOrFail($id);
        $this->assertPending($req);
        $req->update([
            'status' => 'REJECTED',
            'approved_by' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $request->input('note'),
        ]);
        AuditLogger::record($request, "Reject reschedule MPS #{$req->mps_id}");

        return ApiResponse::item($req->load($this->with));
    }

    /** Requester withdraws their own pending request. */
    public function cancel(Request $request, int $id)
    {
        $req = prd_mps_resched::findOrFail($id);
        $this->assertPending($req);
        if ($req->requested_by !== $request->user()->id && ! $request->user()->isSuperAdmin()) {
            throw BizException::make('RESCHED_OWNER', 'Hanya pengaju yang dapat membatalkan permintaannya.');
        }
        $req->update(['status' => 'CANCELLED', 'decided_at' => now()]);
        AuditLogger::record($request, "Cancel reschedule request #{$id}");

        return ApiResponse::item($req->load($this->with));
    }

    private function assertPending(prd_mps_resched $req): void
    {
        if ($req->status !== 'PENDING') {
            throw BizException::make('RESCHED_DONE', 'Permintaan ini sudah diproses.');
        }
    }
}
