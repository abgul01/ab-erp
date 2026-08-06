<?php

namespace App\Http\Controllers\Api\Sales;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\m_item_customer;
use App\Models\sls_so_main;
use App\Support\ApiResponse;
use App\Support\ApprovalEngine;
use App\Support\AuditLogger;
use App\Support\ItemLifecycle;
use App\Support\LineTax;
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
    private array $with = ['cus', 'currency', 'user', 'detail.item', 'detail.tax', 'detail.pph_tax', 'detail.pricelist_det.main'];

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
                'po_date' => $data['po_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'note' => $data['note'] ?? null,
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
                'po_date' => $data['po_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'note' => $data['note'] ?? null,
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

    public function submit(Request $request, int $id)
    {
        $so = sls_so_main::with('detail')->findOrFail($id);
        if ($so->status !== 'DRAFT') {
            throw BizException::make('SO_STATE', 'Hanya SO DRAFT yang dapat disubmit.');
        }
        if ($so->detail->isEmpty()) {
            throw BizException::make('SO_EMPTY', 'SO tanpa baris item tidak dapat disubmit.');
        }
        $so->submitForApproval();
        AuditLogger::record($request, "Submit SO {$so->code}", $so->code);

        return ApiResponse::item($so->load($this->with));
    }

    public function approve(Request $request, int $id)
    {
        $so = sls_so_main::findOrFail($id);
        if ($so->status !== 'SUBMITTED') {
            throw BizException::make('SO_STATE', 'SO harus berstatus SUBMITTED untuk di-approve.');
        }
        $engine = app(ApprovalEngine::class);
        $engine->approve($so, $request->user(), $request->input('note'));

        return ApiResponse::item($so->fresh()->load($this->with));
    }

    public function reject(Request $request, int $id)
    {
        $so = sls_so_main::findOrFail($id);
        $request->validate(['note' => 'required|string|max:300']);
        $engine = app(ApprovalEngine::class);
        $engine->reject($so, $request->user(), $request->input('note'));

        return ApiResponse::item($so->fresh()->load($this->with));
    }

    public function close(Request $request, int $id)
    {
        $so = sls_so_main::findOrFail($id);
        if ($so->status !== 'APPROVED') {
            throw BizException::make('SO_STATE', 'Hanya SO APPROVED yang dapat di-close.');
        }
        $so->update(['status' => 'CLOSED']);
        AuditLogger::record($request, "Close SO {$so->code}", $so->code);

        return ApiResponse::item($so->load($this->with));
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
                throw BizException::make('SO_ITEM', 'Baris #'.($i + 1).': item belum terdaftar untuk customer ini (tab Customer di Item Master).');
            }
        }

        /*
         * Dan part-nya harus sudah lulus uji coba. Menjual barang yang masih
         * trial berarti menjanjikan tanggal kirim atas sesuatu yang belum tentu
         * lolos PPAP — sampel trial dikirim lewat jalurnya sendiri, bukan lewat
         * Sales Order.
         */
        ItemLifecycle::assertMassPro(
            collect($data['lines'] ?? [])->pluck('item_id')->all(),
            'sales order'
        );
    }

    private function syncLines(sls_so_main $so, array $lines): void
    {
        $taxes = DB::table('m_tax')->get()->keyBy('id');
        $defaultPpn = DB::table('m_tax')->where('code', 'PPN-DN')->value('id');
        $defaultPph = DB::table('m_tax')->where('code', 'like', 'PPH-%')->orderBy('id')->value('id');

        foreach ($lines as $l) {
            $ppnOn = (int) ($l['ppn'] ?? 0) === 1;
            $pphOn = (int) ($l['pph'] ?? 0) === 1;
            $ppnId = $l['tax_id'] ?? ($ppnOn ? $defaultPpn : null);
            $pphId = $l['pph_tax_id'] ?? ($pphOn ? $defaultPph : null);

            $subtotal = round(((float) ($l['price'] ?? 0)) * ((int) $l['qty']), 2);
            $tax = LineTax::compute(
                $subtotal,
                $ppnOn && $ppnId ? $taxes->get($ppnId) : null,
                $pphOn && $pphId ? $taxes->get($pphId) : null,
            );

            $so->detail()->create([
                'item_id' => $l['item_id'],
                'po_detail_code' => $l['po_detail_code'] ?? null,
                'qty' => $l['qty'],
                'price' => $l['price'] ?? 0,
                'pricelist_det_id' => $l['pricelist_det_id'] ?? null,
                'tax_id' => $ppnOn ? $ppnId : null,
                'pph_tax_id' => $pphOn ? $pphId : null,
                'dpp' => $tax['dpp'],
                'ppn_value' => $tax['ppn_value'],
                'pph_value' => $tax['pph_value'],
                'local_mat' => (int) ($l['local_mat'] ?? 0),
                'ppn' => (int) $ppnOn,
                'pph' => (int) $pphOn,
                'due_date' => $l['due_date'] ?? null,
                'note' => $l['note'] ?? null,
                'qty_delivered' => 0,
            ]);
        }
    }

    private function validateSo(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],                       // tanggal SO dibuat
            'po_date' => ['nullable', 'date'],                    // tanggal PO customer diterima
            'due_date' => ['nullable', 'date'],                   // due date order
            'cus_id' => ['required', 'integer', 'exists:m_contacts,id'],
            'cus_po_no' => ['nullable', 'string', 'max:50'],
            'currency_id' => ['nullable', 'integer', 'exists:m_currency,id'],
            'note' => ['nullable', 'string', 'max:200'],
            'lines' => ['array'],
            'lines.*.item_id' => ['required', 'integer', 'exists:m_item,id'],
            'lines.*.po_detail_code' => ['nullable', 'string', 'max:50'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_id' => ['nullable', 'integer', 'exists:m_tax,id'],
            'lines.*.pph_tax_id' => ['nullable', 'integer', 'exists:m_tax,id'],
            'lines.*.pricelist_det_id' => ['nullable', 'integer', 'exists:m_pricelist_det,id'],
            'lines.*.local_mat' => ['nullable', 'boolean'],
            'lines.*.ppn' => ['nullable', 'boolean'],
            'lines.*.pph' => ['nullable', 'boolean'],
            'lines.*.due_date' => ['nullable', 'date'],
            'lines.*.note' => ['nullable', 'string', 'max:200'],
        ]);
    }
}
