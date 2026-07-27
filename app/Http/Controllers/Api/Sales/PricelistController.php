<?php

namespace App\Http\Controllers\Api\Sales;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\m_pricelist_det;
use App\Models\m_pricelist_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use App\Support\PricelistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Customer pricelist. One header per customer, and a line per item with its own
 * validity window (valid_from..valid_to) and optional minimum quantity — prices
 * are renegotiated per period, so the SO looks up the line that is valid on the
 * order date. The operator may still type a price by hand on the SO.
 */
class PricelistController extends Controller
{
    private array $with = ['cus', 'detail.item', 'detail.currency'];

    public function index(Request $request)
    {
        $query = m_pricelist_main::with(['cus'])->withCount('detail');
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where('code', 'like', "%{$q}%")
                ->orWhereHas('cus', fn ($s) => $s->where('company_n', 'like', "%{$q}%"));
        }
        if ($st = $request->query('status')) {
            $query->where('status', $st);
        }
        $query->orderByDesc('id');

        return ApiResponse::paginated($query->paginate(min(max((int) $request->query('per_page', 20), 1), 200)));
    }

    public function show(int $id)
    {
        return ApiResponse::item(m_pricelist_main::with($this->with)->findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $this->validatePl($request);

        $pl = DB::transaction(function () use ($data, $request) {
            $pl = m_pricelist_main::create([
                'code' => (new NumberingService)->next('PL', 'PL'),
                'cus_id' => $data['cus_id'],
                'status' => $data['status'] ?? 'ACTIVE',
                'user_id' => $request->user()->id,
            ]);
            $this->syncLines($pl, $data['lines'] ?? []);
            AuditLogger::record($request, "Create Pricelist {$pl->code}", $pl->code);

            return $pl;
        });

        return ApiResponse::item($pl->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $pl = m_pricelist_main::findOrFail($id);
        $data = $this->validatePl($request);

        DB::transaction(function () use ($pl, $data, $request) {
            $pl->update(['cus_id' => $data['cus_id'], 'status' => $data['status'] ?? $pl->status]);
            $pl->detail()->delete();
            $this->syncLines($pl, $data['lines'] ?? []);
            AuditLogger::record($request, "Update Pricelist {$pl->code}", $pl->code);
        });

        return ApiResponse::item($pl->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $pl = m_pricelist_main::findOrFail($id);
        if (DB::table('sls_so_detail')->whereIn('pricelist_det_id', $pl->detail()->pluck('id'))->exists()) {
            throw BizException::make('PL_USED', 'Pricelist ini sudah dipakai pada Sales Order, tidak dapat dihapus.');
        }
        DB::transaction(function () use ($pl, $request) {
            $pl->detail()->delete();
            $pl->delete();
            AuditLogger::record($request, "Delete Pricelist {$pl->code}", $pl->code);
        });

        return ApiResponse::item(['message' => 'Pricelist berhasil dihapus.']);
    }

    /**
     * Price for a customer+item on a date: the ACTIVE pricelist line whose
     * validity covers that date and whose min_qty the order meets. The newest
     * matching window wins when they overlap.
     */
    public function lookup(Request $request)
    {
        $data = $request->validate([
            'cus_id' => ['required', 'integer'],
            'item_id' => ['required', 'integer'],
            'date' => ['nullable', 'date'],
            'qty' => ['nullable', 'integer', 'min:1'],
        ]);
        $row = PricelistService::find(
            (int) $data['cus_id'],
            (int) $data['item_id'],
            $data['date'] ?? now()->toDateString(),
            (int) ($data['qty'] ?? 1),
        );

        return ApiResponse::item($row ? [
            'found' => true,
            'pricelist_det_id' => (int) $row->id, 'pricelist_code' => $row->pricelist_code,
            'price' => (float) $row->price, 'currency_id' => $row->currency_id, 'currency' => $row->currency,
            'valid_from' => $row->valid_from, 'valid_to' => $row->valid_to, 'min_qty' => $row->min_qty,
        ] : ['found' => false]);
    }

    private function syncLines(m_pricelist_main $pl, array $lines): void
    {
        foreach ($lines as $l) {
            $pl->detail()->create([
                'item_id' => $l['item_id'],
                'price' => $l['price'],
                'currency_id' => $l['currency_id'],
                'valid_from' => $l['valid_from'],
                'valid_to' => $l['valid_to'],
                'min_qty' => $l['min_qty'] ?? null,
            ]);
        }
    }

    private function validatePl(Request $request): array
    {
        return $request->validate([
            'cus_id' => ['required', 'integer', 'exists:m_contacts,id'],
            'status' => ['nullable', 'in:ACTIVE,INACTIVE'],
            'lines' => ['array'],
            'lines.*.item_id' => ['required', 'integer', 'exists:m_item,id'],
            'lines.*.price' => ['required', 'numeric', 'min:0'],
            'lines.*.currency_id' => ['required', 'integer', 'exists:m_currency,id'],
            'lines.*.valid_from' => ['required', 'date'],
            'lines.*.valid_to' => ['required', 'date', 'after_or_equal:lines.*.valid_from'],
            'lines.*.min_qty' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}
