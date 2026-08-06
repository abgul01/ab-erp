<?php

namespace App\Http\Controllers\Api\Wms;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\wh_adj_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use App\Support\StockAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stock opname and adjustment.
 *
 * A sheet is drafted, counted, then posted. Posting is the irreversible step —
 * it writes the variance journal — so a draft can be corrected freely and a
 * posted sheet not at all.
 *
 * PRD §4.8
 */
class AdjustmentController extends Controller
{
    public function __construct(private StockAdjustmentService $svc) {}

    public function index(Request $request)
    {
        $q = wh_adj_main::with('user')->withCount('detail')->orderByDesc('id');

        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        if ($type = $request->query('adj_type')) {
            $q->where('adj_type', $type);
        }

        return ApiResponse::paginated($q->paginate(min(max((int) $request->query('per_page', 20), 1), 200)));
    }

    public function show(int $id)
    {
        return ApiResponse::item(wh_adj_main::with(['detail.item', 'user'])->findOrFail($id));
    }

    /**
     * Snapshot of what the system currently holds, so the counter has something
     * to count against rather than typing both figures from memory.
     */
    public function systemStock(Request $request)
    {
        return ApiResponse::collection(
            $this->svc->systemStock($request->query('warehouse', 'RM'))
        );
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $main = DB::transaction(function () use ($data, $request) {
            $main = wh_adj_main::create([
                'code' => (new NumberingService)->next('WH_ADJ', 'ADJ'),
                'date' => $data['date'],
                'adj_type' => $data['adj_type'],
                'warehouse' => $data['warehouse'] ?? 'RM',
                'reason' => $data['reason'] ?? null,
                'status' => 'DRAFT',
                'user_id' => $request->user()->id,
            ]);
            $this->svc->syncLines($main, $data['lines']);

            return $main;
        });

        AuditLogger::record($request, "Buat {$main->adj_type} {$main->code}", $main->code);

        return ApiResponse::item($main->load('detail.item'), 201);
    }

    public function update(Request $request, int $id)
    {
        $main = wh_adj_main::findOrFail($id);
        $this->assertDraft($main);
        $data = $this->validated($request);

        DB::transaction(function () use ($main, $data) {
            $main->update([
                'date' => $data['date'],
                'adj_type' => $data['adj_type'],
                'warehouse' => $data['warehouse'] ?? 'RM',
                'reason' => $data['reason'] ?? null,
            ]);
            $main->detail()->delete();
            $this->svc->syncLines($main, $data['lines']);
        });

        AuditLogger::record($request, "Ubah {$main->code}", $main->code);

        return ApiResponse::item($main->fresh()->load('detail.item'));
    }

    /** Post the variance: stock moves and the journal is written. */
    public function post(Request $request, int $id)
    {
        $main = wh_adj_main::with('detail')->findOrFail($id);
        $this->assertDraft($main);

        $result = $this->svc->post($main, $request->user());
        AuditLogger::record($request, "Posting {$main->code}: selisih nilai {$result['variance_value']}", $main->code);

        return ApiResponse::item($result);
    }

    public function destroy(Request $request, int $id)
    {
        $main = wh_adj_main::findOrFail($id);
        $this->assertDraft($main);

        DB::transaction(function () use ($main) {
            $main->detail()->delete();
            $main->delete();
        });
        AuditLogger::record($request, "Hapus {$main->code}", $main->code);

        return ApiResponse::empty();
    }

    private function assertDraft(wh_adj_main $main): void
    {
        if ($main->status !== 'DRAFT') {
            throw BizException::make('ADJ_LOCKED', 'Dokumen yang sudah diposting tidak dapat diubah.');
        }
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'adj_type' => ['required', 'in:OPNAME,IN,OUT,BEGIN'],
            'warehouse' => ['nullable', 'in:RM,FG,GENERAL'],
            'reason' => ['nullable', 'string', 'max:300'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:m_item,id'],
            'lines.*.serial_id' => ['nullable', 'string', 'max:50'],
            'lines.*.qty_system' => ['nullable', 'numeric'],
            'lines.*.qty_counted' => ['required', 'numeric'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.note' => ['nullable', 'string', 'max:200'],
        ]);
    }
}
