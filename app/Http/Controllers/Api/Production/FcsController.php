<?php

namespace App\Http\Controllers\Api\Production;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\FcsService;
use Illuminate\Http\Request;

/**
 * Final Check Sheet (FCS) — gate before RFG.
 *
 * LLD §4.9, §5.6
 */
class FcsController extends Controller
{
    public function __construct(private FcsService $svc) {}

    public function index(Request $request)
    {
        return ApiResponse::collection($this->svc->list($request->query('status')));
    }

    public function show(int $id)
    {
        $item = $this->svc->get($id);
        if (! $item) {
            return ApiResponse::notFound();
        }

        return ApiResponse::item($item);
    }

    public function create(Request $request)
    {
        $data = $request->validate([
            'wo_id' => ['required', 'integer', 'exists:prd_wo_main,id'],
        ]);

        $id = $this->svc->create((int) $data['wo_id']);
        AuditLogger::record($request, "FCS created for WO #{$data['wo_id']}");

        return ApiResponse::created(['id' => $id]);
    }

    public function approve(Request $request, int $id)
    {
        $result = $this->svc->approve($id);
        AuditLogger::record($request, "FCS #{$id} approved → FG lot {$result['lot_code']}");

        return ApiResponse::item($result);
    }

    public function reject(Request $request, int $id)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->svc->reject($id, $data['reason']);
        AuditLogger::record($request, "FCS #{$id} rejected: {$data['reason']}");

        return ApiResponse::ok(['id' => $id]);
    }

    public function eligibleWOs(Request $request)
    {
        return ApiResponse::collection($this->svc->eligibleWOs());
    }
}
