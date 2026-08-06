<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\QasService;
use Illuminate\Http\Request;

/**
 * Quality inspection for incoming goods (QAS).
 * After GR generates serials, QAS inspects physical length/weight,
 * marks OK/NG, then confirms the GR for warehouse putaway.
 *
 * PRD §4.6, LLD §5.2
 */
class QasController extends Controller
{
    public function __construct(private QasService $qas) {}

    /**
     * GRs pending inspection.
     */
    public function pending(Request $request)
    {
        return ApiResponse::collection($this->qas->pendingGrs());
    }

    /**
     * Serials awaiting inspection for a specific GR.
     */
    public function serials(Request $request, int $grId)
    {
        return ApiResponse::collection($this->qas->pendingSerials($grId));
    }

    /**
     * Inspect a single serial — record actual length/weight and OK/NG status.
     */
    public function inspect(Request $request)
    {
        $data = $request->validate([
            'serial_id' => ['required', 'integer', 'exists:prc_gr_serial,id'],
            'length' => ['required', 'numeric', 'min:0'],
            'weight' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:OK,NG'],
            'ng_reason' => ['nullable', 'string', 'max:100'],
        ]);

        $serial = $this->qas->inspect(
            (int) $data['serial_id'],
            (float) $data['length'],
            (float) $data['weight'],
            $data['status'],
            $data['ng_reason'] ?? null,
        );

        AuditLogger::record($request, "QAS inspect serial {$serial->serial_id} → {$data['status']}", $serial->serial_id);

        return ApiResponse::item($serial);
    }

    /** Inspection plan for an item: which parameters, and within what tolerance. */
    public function plan(Request $request, int $itemId)
    {
        return ApiResponse::collection($this->qas->planFor($itemId));
    }

    /** Readings already recorded against a GR. */
    public function readings(Request $request, int $grId)
    {
        return ApiResponse::collection($this->qas->readings($grId));
    }

    /** Save the measured values behind the verdict. */
    public function saveReadings(Request $request, int $grId)
    {
        $data = $request->validate([
            'readings' => ['required', 'array', 'min:1'],
            'readings.*.param' => ['required', 'string', 'max:50'],
            'readings.*.standard' => ['nullable', 'string', 'max:50'],
            'readings.*.actual' => ['nullable', 'string', 'max:50'],
            'readings.*.judge' => ['nullable', 'in:OK,NG'],
        ]);

        $main = $this->qas->recordReadings($grId, (int) $request->user()->id, $data['readings']);
        AuditLogger::record($request, 'QAS catat '.count($data['readings'])." parameter untuk GR #{$grId}");

        return ApiResponse::item($main);
    }

    /**
     * Confirm inspection — finalise QAS for the GR.
     */
    public function confirm(Request $request, int $grId)
    {
        $this->qas->confirm($grId, (int) $request->user()->id);

        AuditLogger::record($request, "QAS confirm GR #{$grId}");

        return ApiResponse::item(['message' => 'Pengecekan QAS selesai.']);
    }
}
