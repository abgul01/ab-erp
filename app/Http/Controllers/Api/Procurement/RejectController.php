<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\prc_gr_main;
use App\Models\prc_gr_reject;
use App\Models\prc_po_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GR Reject / retur vendor (Fase 2E). A reject references one GR + item with a
 * qty and reason. Lifecycle: DRAFT → RETURNED → CLAIMED. Marking RETURNED
 * decrements the PO's qty_received (goods went back, vendor owes redelivery)
 * and reopens the PO's receiving status.
 */
class RejectController extends Controller
{
    private array $with = ['gr', 'ven', 'item'];

    public function index(Request $request)
    {
        $query = prc_gr_reject::with($this->with);
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where('code', 'like', "%{$q}%");
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        $query->orderByDesc('id');

        return ApiResponse::paginated($query->paginate(min(max((int) $request->query('per_page', 20), 1), 200)));
    }

    public function show(int $id)
    {
        return ApiResponse::item(prc_gr_reject::with($this->with)->findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $this->validateReject($request);
        $gr = prc_gr_main::with('detail')->findOrFail($data['gr_id']);
        $this->assertRejectable($gr, (int) $data['item_id'], (int) $data['qty']);

        $reject = DB::transaction(function () use ($data, $gr, $request) {
            $reject = prc_gr_reject::create([
                'code' => (new NumberingService)->next('GR_REJECT', 'RJ'),
                'date' => $data['date'],
                'gr_id' => $gr->id,
                'ven_id' => $gr->ven_id,
                'item_id' => $data['item_id'],
                'qty' => $data['qty'],
                'reason' => $data['reason'] ?? null,
                'status' => 'DRAFT',
            ]);
            AuditLogger::record($request, "Create GR Reject {$reject->code} (GR {$gr->code})", $reject->code);

            return $reject;
        });

        return ApiResponse::item($reject->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $reject = prc_gr_reject::findOrFail($id);
        $this->assertDraft($reject);
        $data = $this->validateReject($request);
        $gr = prc_gr_main::with('detail')->findOrFail($data['gr_id']);
        $this->assertRejectable($gr, (int) $data['item_id'], (int) $data['qty'], $reject->id);

        $reject->update([
            'date' => $data['date'],
            'gr_id' => $gr->id,
            'ven_id' => $gr->ven_id,
            'item_id' => $data['item_id'],
            'qty' => $data['qty'],
            'reason' => $data['reason'] ?? null,
        ]);
        AuditLogger::record($request, "Update GR Reject {$reject->code}", $reject->code);

        return ApiResponse::item($reject->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $reject = prc_gr_reject::findOrFail($id);
        $this->assertDraft($reject);
        $reject->delete();
        AuditLogger::record($request, "Delete GR Reject {$reject->code}", $reject->code);

        return ApiResponse::item(['message' => 'Reject berhasil dihapus.']);
    }

    /** Mark goods physically returned: PO owed again (qty_received turun, status reopen). */
    public function markReturned(Request $request, int $id)
    {
        $reject = prc_gr_reject::with('gr')->findOrFail($id);
        if ($reject->status !== 'DRAFT') {
            throw BizException::make('RJ_BAD_STATE', 'Hanya reject DRAFT yang dapat ditandai RETURNED.');
        }

        DB::transaction(function () use ($reject, $request) {
            // GR may span multiple POs: resolve via the GR detail line's own po_id.
            $poId = DB::table('prc_gr_detail')->where('id_prim', $reject->gr_id)
                ->where('item_id', $reject->item_id)->value('po_id');
            $po = $poId ? prc_po_main::with('detail')->find($poId) : null;
            if ($po) {
                GrController::bumpReceived($po, (int) $reject->item_id, -(int) $reject->qty);
                PoController::syncReceivingStatus($po, $request->user()->id);
            }
            $reject->update(['status' => 'RETURNED']);
            AuditLogger::record($request, "Return GR Reject {$reject->code}", $reject->code);
        });

        return ApiResponse::item($reject->load($this->with));
    }

    public function markClaimed(Request $request, int $id)
    {
        $reject = prc_gr_reject::findOrFail($id);
        if ($reject->status !== 'RETURNED') {
            throw BizException::make('RJ_BAD_STATE', 'Hanya reject RETURNED yang dapat ditandai CLAIMED.');
        }
        $reject->update(['status' => 'CLAIMED']);
        AuditLogger::record($request, "Claim GR Reject {$reject->code}", $reject->code);

        return ApiResponse::item($reject->load($this->with));
    }

    private function assertDraft(prc_gr_reject $reject): void
    {
        if ($reject->status !== 'DRAFT') {
            throw BizException::make('RJ_LOCKED', 'Reject yang sudah diproses tidak dapat diubah.');
        }
    }

    /** Reject qty must fit within the GR's received qty for that item, minus other active rejects. */
    private function assertRejectable(prc_gr_main $gr, int $itemId, int $qty, ?int $ignoreId = null): void
    {
        $received = (int) $gr->detail->where('item_id', $itemId)->sum('qty');
        if ($received <= 0) {
            throw BizException::make('RJ_ITEM', 'Item tersebut tidak ada pada GR terpilih.');
        }
        $already = (int) prc_gr_reject::where('gr_id', $gr->id)->where('item_id', $itemId)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->whereIn('status', ['DRAFT', 'RETURNED', 'CLAIMED'])->sum('qty');
        if ($qty + $already > $received) {
            throw BizException::make('RJ_QTY', "Qty reject ({$qty}) + reject sebelumnya ({$already}) melebihi qty diterima ({$received}).");
        }
    }

    private function validateReject(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'gr_id' => ['required', 'integer', 'exists:prc_gr_main,id'],
            'item_id' => ['required', 'integer', 'exists:m_item,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);
    }
}
