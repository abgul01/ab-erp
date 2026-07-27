<?php

namespace App\Http\Controllers\Api\Sales;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\sls_do_main;
use App\Models\sls_return;
use App\Models\tr_inc_fg_det;
use App\Models\tr_inc_fg_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sales Return (Fase 5). Goods that were shipped on a Delivery Order come back:
 * one return = one DO line item + qty. Posting restocks the finished goods
 * (writes tr_inc_fg), so returned pieces are available again. Return qty is
 * capped at what that DO delivered, less anything already returned.
 */
class SalesReturnController extends Controller
{
    private array $with = ['do.so.cus', 'item'];

    public function index(Request $request)
    {
        $rows = sls_return::query()->with(['do', 'item'])
            ->when($request->query('q'), fn ($q, $s) => $q->where('code', 'like', "%{$s}%"))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        return ApiResponse::item(sls_return::with($this->with)->findOrFail($id));
    }

    /** DOs already shipped (returnable), for the return picker. */
    public function shippedDos()
    {
        $rows = sls_do_main::with(['so.cus', 'detail.item'])
            ->whereIn('status', ['SHIPPED', 'RECEIVED', 'INVOICED'])
            ->orderByDesc('id')->get();

        return ApiResponse::collection($rows->map(fn ($do) => [
            'id' => $do->id, 'code' => $do->code,
            'customer' => $do->so?->cus?->company_n,
            'lines' => $do->detail->map(fn ($d) => [
                'item_id' => $d->item_id, 'item_code' => $d->item?->code, 'part_name' => $d->item?->part_name,
                'delivered' => (int) $d->qty,
                'returnable' => max(0, (int) $d->qty - $this->returnedQty($do->id, $d->item_id)),
            ])->filter(fn ($l) => $l['returnable'] > 0)->values(),
        ])->filter(fn ($r) => $r['lines']->isNotEmpty())->values());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'do_id' => ['required', 'integer', 'exists:sls_do_main,id'],
            'item_id' => ['required', 'integer', 'exists:m_item,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);
        $this->assertReturnable($data['do_id'], $data['item_id'], $data['qty']);

        $ret = sls_return::create([
            'code' => (new NumberingService)->next('SRET', 'SR'),
            'date' => $data['date'],
            'do_id' => $data['do_id'],
            'item_id' => $data['item_id'],
            'qty' => $data['qty'],
            'reason' => $data['reason'] ?? null,
            'status' => 'DRAFT',
        ]);
        AuditLogger::record($request, "Create Sales Return {$ret->code}", $ret->code);

        return ApiResponse::item($ret->load($this->with), 201);
    }

    public function destroy(Request $request, int $id)
    {
        $ret = sls_return::findOrFail($id);
        if ($ret->status !== 'DRAFT') {
            throw BizException::make('SR_LOCKED', 'Retur yang sudah diposting tidak dapat dihapus.');
        }
        $ret->delete();
        AuditLogger::record($request, "Delete Sales Return {$ret->code}", $ret->code);

        return ApiResponse::item(['message' => 'Retur dihapus.']);
    }

    /** POST — restock the returned finished goods and lock the return. */
    public function post(Request $request, int $id)
    {
        $ret = sls_return::findOrFail($id);
        if ($ret->status !== 'DRAFT') {
            throw BizException::make('SR_STATE', 'Retur harus DRAFT untuk diposting.');
        }
        // re-check the cap at commit (other returns may have landed since)
        $this->assertReturnable($ret->do_id, $ret->item_id, $ret->qty, $ret->id);

        DB::transaction(function () use ($ret, $request) {
            $main = tr_inc_fg_main::create([
                'code' => (new NumberingService)->next('FGRET', 'FGR'),
                'date' => now()->toDateString(),
                'user_id' => sprintf('U%04d', $request->user()->id),
            ]);
            tr_inc_fg_det::create([
                'code' => substr("RET-{$ret->id}", 0, 20),
                'main_id' => $main->id,
                'pal_pro_code' => substr("RET-{$ret->code}", 0, 20),
                'item_id' => $ret->item_id,
                'cut_id' => 0,
                'qty' => (int) $ret->qty,
                'wip_id' => null,
            ]);
            $ret->update(['status' => 'POSTED']);
            AuditLogger::record($request, "Post Sales Return {$ret->code} → FG in {$main->code}", $ret->code);
        });

        return ApiResponse::item($ret->load($this->with));
    }

    /* ---------------- helpers ---------------- */

    private function assertReturnable(int $doId, int $itemId, int $qty, ?int $excludeId = null): void
    {
        $do = sls_do_main::with('detail')->findOrFail($doId);
        if (! in_array($do->status, ['SHIPPED', 'RECEIVED', 'INVOICED'], true)) {
            throw BizException::make('SR_DO', 'Hanya DO yang sudah dikirim yang dapat diretur.');
        }
        $delivered = (int) $do->detail->where('item_id', $itemId)->sum('qty');
        if ($delivered <= 0) {
            throw BizException::make('SR_ITEM', 'Item ini tidak ada pada DO tersebut.');
        }
        $already = $this->returnedQty($doId, $itemId, $excludeId);
        if ($qty > $delivered - $already) {
            throw BizException::make('SR_OVER', 'Qty retur melebihi sisa yang dapat diretur (' . ($delivered - $already) . ').');
        }
    }

    /** Qty already returned for a DO line (optionally excluding one return row). */
    private function returnedQty(int $doId, int $itemId, ?int $excludeId = null): int
    {
        return (int) sls_return::where('do_id', $doId)->where('item_id', $itemId)
            ->when($excludeId, fn ($q) => $q->where('id', '<>', $excludeId))
            ->sum('qty');
    }
}
