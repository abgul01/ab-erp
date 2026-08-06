<?php

namespace App\Http\Controllers\Api\Production;

use App\Http\Controllers\Controller;
use App\Models\prd_kanban;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\MaterialIssueService;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Kanban material issue (PP-HACV). Created from a Work Order's RM booking,
 * then issued on the shop floor by scanning serial numbers.
 *
 * LLD §5.3, PRD §4.8
 */
class KanbanController extends Controller
{
    public function __construct(
        private NumberingService $num,
        private MaterialIssueService $issueSvc,
    ) {}

    /**
     * List kanbans.
     */
    public function index(Request $request)
    {
        $query = prd_kanban::with(['wo.fg', 'item']);
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($woId = $request->query('wo_id')) {
            $query->where('wo_id', $woId);
        }
        $query->orderByDesc('id');

        return ApiResponse::paginated($query->paginate(min(max((int) $request->query('per_page', 20), 1), 200)));
    }

    /**
     * Show a kanban.
     */
    public function show(int $id)
    {
        return ApiResponse::item(prd_kanban::with(['wo.fg', 'item'])->findOrFail($id));
    }

    /**
     * Create kanban(s) from a WO's RM booking.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'wo_id' => ['required', 'integer', 'exists:prd_wo_main,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:m_item,id'],
            'items.*.rm_detail_id' => ['nullable', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);

        $kanbans = DB::transaction(function () use ($data, $request) {
            $rows = [];
            foreach ($data['items'] as $item) {
                $rows[] = prd_kanban::create([
                    'code' => $this->num->next('KB', 'KB'),
                    'wo_id' => $data['wo_id'],
                    'item_id' => $item['item_id'],
                    'rm_detail_id' => $item['rm_detail_id'] ?? null,
                    'qty_planned' => $item['qty'],
                    'qty_issued' => 0,
                    'status' => 'OPEN',
                    'created_by' => $request->user()->id,
                ]);
            }

            return $rows;
        });

        AuditLogger::record($request, 'Create '.count($kanbans)." kanban(s) for WO #{$data['wo_id']}");

        return ApiResponse::collection($kanbans, 201);
    }

    /**
     * Issue RM serial against a kanban.
     */
    public function issue(Request $request, int $id)
    {
        $data = $request->validate([
            'serial_id' => ['required', 'string', 'max:50'],
            'length_used' => ['nullable', 'numeric', 'min:0'],
            'rem' => ['nullable', 'boolean'],
            'rem_rack_id' => ['nullable', 'integer', 'exists:m_rack,id'],
        ]);

        $used = (float) ($data['length_used'] ?? 0);
        $returnRem = ! empty($data['rem']);
        if ($returnRem && empty($data['rem_rack_id'])) {
            return response()->json(['errors' => [['code' => 'KANBAN_REMRACK', 'message' => 'Pilih rak remnant untuk pengembalian sisa.']]], 422);
        }

        $result = $this->issueSvc->issue(
            $id,
            $data['serial_id'],
            $used,
            $returnRem,
            $data['rem_rack_id'] ?? null,
            $request->user(),
        );

        AuditLogger::record($request, "Issue kanban #{$id}: serial {$data['serial_id']}");

        return ApiResponse::item($result);
    }

    /**
     * Close a kanban (mark all remaining as returned).
     */
    public function close(Request $request, int $id)
    {
        $kanban = prd_kanban::findOrFail($id);
        $kanban->update([
            'status' => 'CLOSED',
            'closed_by' => $request->user()->id,
            'closed_at' => now(),
        ]);

        AuditLogger::record($request, "Close kanban #{$id}");

        return ApiResponse::item($kanban);
    }
}
