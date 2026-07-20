<?php

namespace App\Http\Controllers\Api\Sales;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\m_item_customer;
use App\Models\sls_so_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sales Order (Fase 5 Order Mgmt). Header sls_so_main + lines sls_so_detail.
 * Every ordered item must be registered to the customer in m_item_customer.
 * Lifecycle: DRAFT → APPROVED → CLOSED / CANCELLED. Feeds MPP generation.
 */
class SoController extends Controller
{
    private array $with = ['cus', 'currency', 'user', 'detail.item', 'detail.tax'];

    public function index(Request $request)
    {
        $query = sls_so_main::with(['cus', 'currency'])->withCount('detail');
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
        return ApiResponse::item(sls_so_main::with($this->with)->findOrFail($id));
    }

    /** Items registered to a customer (m_item_customer) — for the SO line picker. */
    public function customerItems(int $cusId)
    {
        $rows = m_item_customer::with('item')->where('cus_id', $cusId)->where('active', 1)->get()
            ->map(fn ($r) => $r->item ? $r->item->only(['id', 'code', 'part_name', 'o_d', 'i_d', 'thick', 'width', 'height']) : null)
            ->filter()->values();

        return ApiResponse::collection($rows);
    }

    public function store(Request $request)
    {
        $data = $this->validateSo($request);
        $this->assertCustomerItems($data);

        $so = DB::transaction(function () use ($data, $request) {
            $so = sls_so_main::create([
                'code' => (new NumberingService)->next('SO', 'SO'),
                'date' => $data['date'],
                'cus_id' => $data['cus_id'],
                'cus_po_no' => $data['cus_po_no'] ?? null,
                'currency_id' => $data['currency_id'] ?? null,
                'user_id' => $request->user()->id,
                'status' => 'DRAFT',
            ]);
            $this->syncLines($so, $data['lines'] ?? []);
            AuditLogger::record($request, "Create SO {$so->code}", $so->code);

            return $so;
        });

        return ApiResponse::item($so->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $so = sls_so_main::findOrFail($id);
        $this->assertDraft($so);
        $data = $this->validateSo($request);
        $this->assertCustomerItems($data);

        DB::transaction(function () use ($so, $data, $request) {
            $so->update([
                'date' => $data['date'],
                'cus_id' => $data['cus_id'],
                'cus_po_no' => $data['cus_po_no'] ?? null,
                'currency_id' => $data['currency_id'] ?? null,
            ]);
            $so->detail()->delete();
            $this->syncLines($so, $data['lines'] ?? []);
            AuditLogger::record($request, "Update SO {$so->code}", $so->code);
        });

        return ApiResponse::item($so->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $so = sls_so_main::findOrFail($id);
        $this->assertDraft($so);
        DB::transaction(function () use ($so, $request) {
            $so->detail()->delete();
            $so->delete();
            AuditLogger::record($request, "Delete SO {$so->code}", $so->code);
        });

        return ApiResponse::item(['message' => 'SO berhasil dihapus.']);
    }

    public function approve(Request $request, int $id)
    {
        return $this->transition($request, $id, 'DRAFT', 'APPROVED', 'Approve');
    }

    public function close(Request $request, int $id)
    {
        return $this->transition($request, $id, 'APPROVED', 'CLOSED', 'Close');
    }

    public function cancel(Request $request, int $id)
    {
        $so = sls_so_main::findOrFail($id);
        if (! in_array($so->status, ['DRAFT', 'APPROVED'], true)) {
            throw BizException::make('SO_STATE', 'SO ini tidak dapat dibatalkan.');
        }
        $so->update(['status' => 'CANCELLED']);
        AuditLogger::record($request, "Cancel SO {$so->code}", $so->code);

        return ApiResponse::item($so->load($this->with));
    }

    private function transition(Request $request, int $id, string $from, string $to, string $verb)
    {
        $so = sls_so_main::with('detail')->findOrFail($id);
        if ($so->status !== $from) {
            throw BizException::make('SO_STATE', "SO harus berstatus {$from} untuk {$verb}.");
        }
        if ($to === 'APPROVED' && $so->detail->isEmpty()) {
            throw BizException::make('SO_EMPTY', 'SO tanpa baris item tidak dapat di-approve.');
        }
        $so->update(['status' => $to]);
        AuditLogger::record($request, "{$verb} SO {$so->code}", $so->code);

        return ApiResponse::item($so->load($this->with));
    }

    private function assertDraft(sls_so_main $so): void
    {
        if ($so->status !== 'DRAFT') {
            throw BizException::make('SO_LOCKED', 'SO yang sudah di-approve tidak dapat diubah.');
        }
    }

    /** Every line item must be registered to this customer (m_item_customer, active). */
    private function assertCustomerItems(array $data): void
    {
        $allowed = m_item_customer::where('cus_id', $data['cus_id'])->where('active', 1)->pluck('item_id')->all();
        foreach ($data['lines'] ?? [] as $i => $l) {
            if (! in_array((int) $l['item_id'], $allowed, true)) {
                throw BizException::make('SO_ITEM', 'Baris #' . ($i + 1) . ': item belum terdaftar untuk customer ini (tab Customer di Item Master).');
            }
        }
    }

    private function syncLines(sls_so_main $so, array $lines): void
    {
        foreach ($lines as $l) {
            $so->detail()->create([
                'item_id' => $l['item_id'],
                'qty' => $l['qty'],
                'price' => $l['price'] ?? 0,
                'tax_id' => $l['tax_id'] ?? null,
                'due_date' => $l['due_date'] ?? null,
                'qty_delivered' => 0,
            ]);
        }
    }

    private function validateSo(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'cus_id' => ['required', 'integer', 'exists:m_contacts,id'],
            'cus_po_no' => ['nullable', 'string', 'max:50'],
            'currency_id' => ['nullable', 'integer', 'exists:m_currency,id'],
            'lines' => ['array'],
            'lines.*.item_id' => ['required', 'integer', 'exists:m_item,id'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_id' => ['nullable', 'integer', 'exists:m_tax,id'],
            'lines.*.due_date' => ['nullable', 'date'],
        ]);
    }
}
