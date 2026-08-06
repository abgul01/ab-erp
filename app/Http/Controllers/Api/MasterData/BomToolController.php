<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\BomToolService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BomToolController extends Controller
{
    public function __construct(private BomToolService $svc) {}

    /** Every BOM with its owning item — the picker for compare and copy. */
    public function list()
    {
        return ApiResponse::collection(
            DB::table('m_bom as b')
                ->join('m_item as i', 'i.id', '=', 'b.item_id')
                ->orderBy('i.code')
                ->get(['b.id', 'b.item_id', 'b.active', 'i.code as item_code', 'i.part_name'])
        );
    }

    public function whereUsed(int $itemId)
    {
        return ApiResponse::collection($this->svc->whereUsed($itemId));
    }

    public function copy(Request $request)
    {
        $data = $request->validate([
            'source_bom_id' => ['required', 'integer', 'exists:m_bom,id'],
            'target_item_id' => ['required', 'integer', 'exists:m_item,id'],
        ]);

        $bom = $this->svc->copy((int) $data['source_bom_id'], (int) $data['target_item_id']);
        AuditLogger::record($request, "BOM copied #{$data['source_bom_id']} → item #{$data['target_item_id']} (new BOM #{$bom->id})");

        return ApiResponse::item($bom, 201);
    }

    public function compare(Request $request)
    {
        $data = $request->validate([
            'bom_id_a' => ['required', 'integer', 'exists:m_bom,id'],
            'bom_id_b' => ['required', 'integer', 'exists:m_bom,id'],
        ]);

        return ApiResponse::item($this->svc->compare((int) $data['bom_id_a'], (int) $data['bom_id_b']));
    }
}
