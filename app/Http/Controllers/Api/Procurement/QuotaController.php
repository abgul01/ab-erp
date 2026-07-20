<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Models\m_quota;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\QuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Import Quota master (Fase 2D). m_quota header + m_quota_item (allowed items).
 * Live balance is derived from the prc_quota_txn ledger via QuotaService.
 */
class QuotaController extends Controller
{
    private array $with = ['items.item'];

    public function index(Request $request)
    {
        $query = m_quota::withCount('items')->with('items:id,quota_id,item_id');
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where('code', 'like', "%{$q}%")->orWhere('descrip', 'like', "%{$q}%");
        }
        $query->orderByDesc('id');
        $page = $query->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        $svc = new QuotaService;
        $page->getCollection()->transform(function ($q) use ($svc) {
            $q->balance_ton = $svc->balance($q->id);
            return $q;
        });

        return ApiResponse::paginated($page);
    }

    public function show(int $id)
    {
        $quota = m_quota::with($this->with)->findOrFail($id);
        $svc = new QuotaService;
        $data = $quota->toArray();
        $data['balance_ton'] = $svc->balance($id);
        $data['used_ton'] = round((float) $quota->total_ton - $data['balance_ton'], 3);

        return ApiResponse::item($data);
    }

    public function balance(int $id)
    {
        $quota = m_quota::findOrFail($id);
        $balance = (new QuotaService)->balance($id);

        return ApiResponse::item([
            'quota_id' => $id,
            'total_ton' => (float) $quota->total_ton,
            'balance_ton' => $balance,
            'used_ton' => round((float) $quota->total_ton - $balance, 3),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateQuota($request);
        $quota = DB::transaction(function () use ($data, $request) {
            $quota = m_quota::create([
                'code' => $data['code'],
                'descrip' => $data['descrip'] ?? null,
                'hs_code' => $data['hs_code'] ?? null,
                'total_ton' => $data['total_ton'],
                'valid_from' => $data['valid_from'],
                'valid_to' => $data['valid_to'],
                'active' => (int) ($data['active'] ?? 1),
            ]);
            $this->syncItems($quota, $data['item_ids'] ?? []);
            AuditLogger::record($request, "Create Quota {$quota->code}", $quota->code);

            return $quota;
        });

        return ApiResponse::item($quota->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $quota = m_quota::findOrFail($id);
        $data = $this->validateQuota($request, $id);
        DB::transaction(function () use ($quota, $data, $request) {
            $quota->update([
                'code' => $data['code'],
                'descrip' => $data['descrip'] ?? null,
                'hs_code' => $data['hs_code'] ?? null,
                'total_ton' => $data['total_ton'],
                'valid_from' => $data['valid_from'],
                'valid_to' => $data['valid_to'],
                'active' => (int) ($data['active'] ?? 1),
            ]);
            $quota->items()->delete();
            $this->syncItems($quota, $data['item_ids'] ?? []);
            AuditLogger::record($request, "Update Quota {$quota->code}", $quota->code);
        });

        return ApiResponse::item($quota->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $quota = m_quota::findOrFail($id);
        DB::transaction(function () use ($quota, $request) {
            $quota->items()->delete();
            $quota->delete();
            AuditLogger::record($request, "Delete Quota {$quota->code}", $quota->code);
        });

        return ApiResponse::item(['message' => 'Kuota berhasil dihapus.']);
    }

    private function syncItems(m_quota $quota, array $itemIds): void
    {
        foreach (array_unique(array_map('intval', $itemIds)) as $itemId) {
            $quota->items()->create(['item_id' => $itemId]);
        }
    }

    private function validateQuota(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('m_quota', 'code')->ignore($id)],
            'descrip' => ['nullable', 'string', 'max:300'],
            'hs_code' => ['nullable', 'string', 'max:20'],
            'total_ton' => ['required', 'numeric', 'min:0'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['required', 'date', 'after_or_equal:valid_from'],
            'active' => ['nullable', 'boolean'],
            'item_ids' => ['array'],
            'item_ids.*' => ['integer', 'exists:m_item,id'],
        ]);
    }
}
