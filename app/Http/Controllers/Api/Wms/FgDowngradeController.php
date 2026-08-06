<?php

namespace App\Http\Controllers\Api\Wms;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\FgDowngradeService;
use Illuminate\Http\Request;

/**
 * FG Downgrade: reclassify FG items back to raw material.
 *
 * LLD §5.7
 */
class FgDowngradeController extends Controller
{
    public function __construct(private FgDowngradeService $svc) {}

    public function mappings(Request $request)
    {
        return ApiResponse::collection($this->svc->mappings());
    }

    public function downgrade(Request $request)
    {
        $data = $request->validate([
            'det_id' => ['required', 'integer', 'exists:tr_inc_fg_det,id'],
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->svc->downgrade(
            (int) $data['det_id'],
            (int) $data['qty'],
        );

        AuditLogger::record($request, "FG Downgrade det #{$data['det_id']} → material #{$result['material_item_id']} qty {$data['qty']}");

        return ApiResponse::item($result);
    }
}
