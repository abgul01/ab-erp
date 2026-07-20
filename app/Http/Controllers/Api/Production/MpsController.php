<?php

namespace App\Http\Controllers\Api\Production;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\prd_mpp;
use App\Models\prd_mps;
use App\Models\prd_wo_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

/**
 * Master Production Schedule (Fase 4 APS). Dated schedule entries per FG, capped
 * by the approved MPP for that item+period. Lifecycle: DRAFT → APPROVED (only
 * approved MPS can be turned into a Work Order).
 */
class MpsController extends Controller
{
    public function index(Request $request)
    {
        $query = prd_mps::with(['item', 'machine']);
        if ($q = trim((string) $request->query('q', ''))) {
            $query->whereHas('item', fn ($s) => $s->where('code', 'like', "%{$q}%")->orWhere('part_name', 'like', "%{$q}%"));
        }
        if ($st = $request->query('status')) {
            $query->where('status', $st);
        }
        $query->orderByDesc('plan_date')->orderBy('item_id');
        $page = $query->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        // annotate remaining WO capacity so the WO screen can pick open MPS
        $page->getCollection()->transform(function ($m) {
            $m->wo_qty = (int) prd_wo_main::where('mps_id', $m->id)->where('status', '<>', 9)->sum('qty');
            $m->wo_remaining = max(0, (int) $m->qty - $m->wo_qty);
            return $m;
        });

        return ApiResponse::paginated($page);
    }

    public function show(int $id)
    {
        $mps = prd_mps::with(['item', 'machine'])->findOrFail($id);
        $period = $this->periodOf($mps->plan_date);
        $data = $mps->toArray();
        $data['period'] = $period;
        $data['mpp_qty'] = (int) prd_mpp::where('item_id', $mps->item_id)->where('period', $period)->where('status', 'APPROVED')->value('plan_qty');
        $data['wo_qty'] = (int) prd_wo_main::where('mps_id', $mps->id)->where('status', '<>', 9)->sum('qty');
        $data['wo_remaining'] = max(0, (int) $mps->qty - $data['wo_qty']);

        return ApiResponse::item($data);
    }

    public function store(Request $request)
    {
        $data = $this->validateMps($request);
        $this->assertWithinMpp($data['item_id'], $data['plan_date'], $data['qty'], null);

        $mps = prd_mps::create([...$data, 'status' => 'DRAFT']);
        AuditLogger::record($request, "Create MPS {$mps->plan_date} item#{$mps->item_id}");

        return ApiResponse::item($mps->load(['item', 'machine']), 201);
    }

    public function update(Request $request, int $id)
    {
        $mps = prd_mps::findOrFail($id);
        $this->assertDraft($mps);
        $data = $this->validateMps($request);
        $this->assertWithinMpp($data['item_id'], $data['plan_date'], $data['qty'], $id);

        $mps->update($data);
        AuditLogger::record($request, "Update MPS #{$id}");

        return ApiResponse::item($mps->load(['item', 'machine']));
    }

    public function destroy(Request $request, int $id)
    {
        $mps = prd_mps::findOrFail($id);
        $this->assertDraft($mps);
        if (prd_wo_main::where('mps_id', $id)->exists()) {
            throw BizException::make('MPS_HAS_WO', 'MPS ini sudah punya Work Order, tidak dapat dihapus.');
        }
        $mps->delete();
        AuditLogger::record($request, "Delete MPS #{$id}");

        return ApiResponse::item(['message' => 'MPS berhasil dihapus.']);
    }

    public function approve(Request $request, int $id)
    {
        $mps = prd_mps::findOrFail($id);
        if ($mps->status !== 'DRAFT') {
            throw BizException::make('MPS_STATE', 'Hanya MPS DRAFT yang dapat di-approve.');
        }
        $mps->update(['status' => 'APPROVED']);
        AuditLogger::record($request, "Approve MPS #{$id}");

        return ApiResponse::item($mps->load(['item', 'machine']));
    }

    private function periodOf(string $date): string
    {
        return substr($date, 0, 4) . substr($date, 5, 2);
    }

    /** MPS total for an item+period must not exceed the approved MPP plan. */
    private function assertWithinMpp(int $itemId, string $date, int $qty, ?int $excludeId): void
    {
        $period = $this->periodOf($date);
        $mpp = prd_mpp::where('item_id', $itemId)->where('period', $period)->where('status', 'APPROVED')->first();
        if (! $mpp) {
            throw BizException::make('MPS_NO_MPP', "Belum ada MPP approved untuk item ini di periode {$period}. Buat & approve MPP dulu.");
        }
        $existing = (int) prd_mps::where('item_id', $itemId)
            ->where('plan_date', 'like', substr($period, 0, 4) . '-' . substr($period, 4, 2) . '-%')
            ->when($excludeId, fn ($q) => $q->where('id', '<>', $excludeId))
            ->sum('qty');
        if ($existing + $qty > (int) $mpp->plan_qty) {
            throw BizException::make('MPS_OVER', "Total MPS ({$existing}+{$qty}) melebihi rencana MPP ({$mpp->plan_qty}) periode {$period}.");
        }
    }

    private function assertDraft(prd_mps $mps): void
    {
        if ($mps->status !== 'DRAFT') {
            throw BizException::make('MPS_LOCKED', 'MPS yang sudah approved tidak dapat diubah.');
        }
    }

    private function validateMps(Request $request): array
    {
        return $request->validate([
            'plan_date' => ['required', 'date'],
            'item_id' => ['required', 'integer', 'exists:m_item,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'machine_id' => ['nullable', 'integer'],
        ]);
    }
}
