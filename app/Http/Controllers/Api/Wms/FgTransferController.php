<?php

namespace App\Http\Controllers\Api\Wms;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\FgTransferService;
use Illuminate\Http\Request;

/**
 * FG Transfer: move FG qty between items of the same spec_group.
 *
 * LLD §5.7
 */
class FgTransferController extends Controller
{
    public function __construct(private FgTransferService $svc) {}

    public function lots(Request $request)
    {
        $itemId = $request->query('item_id') ? (int) $request->query('item_id') : null;

        return ApiResponse::collection($this->svc->availableLots($itemId));
    }

    public function transfer(Request $request)
    {
        $data = $request->validate([
            'from_det_id' => ['required', 'integer', 'exists:tr_inc_fg_det,id'],
            'to_item_id' => ['required', 'integer', 'exists:m_item,id'],
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->svc->transfer(
            (int) $data['from_det_id'],
            (int) $data['to_item_id'],
            (int) $data['qty'],
        );

        AuditLogger::record($request, "FG Transfer det #{$data['from_det_id']} → item #{$data['to_item_id']} qty {$data['qty']}");

        return ApiResponse::item($result);
    }
}
