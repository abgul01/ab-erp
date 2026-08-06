<?php

namespace App\Http\Controllers\Api\Wms;

use App\Http\Controllers\Controller;
use App\Models\prd_wo_serial_rm;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\ScrapService;
use Illuminate\Http\Request;

/**
 * Scrap RM — the manual half of the rule.
 *
 * The system proposes candidates by remaining length; a supervisor confirms or
 * overrules them, in either direction, and the reason is kept.
 *
 * PRD §5 poin 3, LLD §5.4
 */
class ScrapController extends Controller
{
    public function __construct(private ScrapService $svc) {}

    public function index(Request $request)
    {
        $woId = $request->query('wo_id') ? (int) $request->query('wo_id') : null;

        return ApiResponse::collection($this->svc->candidates($woId));
    }

    public function history(int $id)
    {
        return ApiResponse::collection($this->svc->history($id));
    }

    public function decide(Request $request, int $id)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:SCRAP,USABLE'],
            'reason' => ['required', 'string', 'max:300'],
        ]);

        $serial = prd_wo_serial_rm::with('detail')->findOrFail($id);
        $decision = $this->svc->decide($serial, $data['decision'], $data['reason'], $request->user());

        AuditLogger::record(
            $request,
            "Scrap decision {$data['decision']} untuk serial {$serial->serial_id}: {$data['reason']}",
            $serial->serial_id
        );

        return ApiResponse::item($decision);
    }
}
