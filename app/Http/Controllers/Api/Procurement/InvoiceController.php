<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\prc_inv_detail;
use App\Models\prc_inv_main;
use App\Models\prc_po_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AP Invoice vendor (Fase 2G). Vendor invoice number is entered HERE (not at GR).
 * Lines reference GR receipt lines (3-way: PO ↔ GR ↔ Invoice). dpp = Σ(qty×price);
 * total = dpp + vat − wht23. Lifecycle: DRAFT → MATCHED (3-way check) → POSTED.
 * due_date defaults from invoice date + PO TOP.
 */
class InvoiceController extends Controller
{
    private array $with = ['ven', 'po', 'user', 'detail.grDetail.item', 'detail.grDetail.main'];

    public function index(Request $request)
    {
        $query = prc_inv_main::with(['ven', 'po'])->withCount('detail');
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(fn ($s) => $s->where('code', 'like', "%{$q}%")->orWhere('inv_no', 'like', "%{$q}%"));
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        $query->orderByDesc('id');

        return ApiResponse::paginated($query->paginate(min(max((int) $request->query('per_page', 20), 1), 200)));
    }

    public function show(int $id)
    {
        return ApiResponse::item(prc_inv_main::with($this->with)->findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $this->validateInvoice($request);

        $inv = DB::transaction(function () use ($data, $request) {
            $inv = prc_inv_main::create([
                'code' => (new NumberingService)->next('AP_INV', 'AP'),
                ...$this->header($data),
                'user_id' => $request->user()->id,
                'status' => 'DRAFT',
            ]);
            $this->syncLines($inv, $data['lines'] ?? []);
            $this->recalcTotals($inv, $data);
            AuditLogger::record($request, "Create AP Invoice {$inv->code} ({$inv->inv_no})", $inv->code);

            return $inv;
        });

        return ApiResponse::item($inv->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $inv = prc_inv_main::findOrFail($id);
        $this->assertDraft($inv);
        $data = $this->validateInvoice($request);

        DB::transaction(function () use ($inv, $data, $request) {
            $inv->update($this->header($data));
            $inv->detail()->delete();
            $this->syncLines($inv, $data['lines'] ?? []);
            $this->recalcTotals($inv, $data);
            AuditLogger::record($request, "Update AP Invoice {$inv->code}", $inv->code);
        });

        return ApiResponse::item($inv->fresh()->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $inv = prc_inv_main::findOrFail($id);
        $this->assertDraft($inv);
        DB::transaction(function () use ($inv, $request) {
            $inv->detail()->delete();
            $inv->delete();
            AuditLogger::record($request, "Delete AP Invoice {$inv->code}", $inv->code);
        });

        return ApiResponse::item(['message' => 'Invoice berhasil dihapus.']);
    }

    /** 3-way match: every line must fit the GR receipt (and not over-invoice it). */
    public function match(Request $request, int $id)
    {
        $inv = prc_inv_main::with('detail.grDetail.main')->findOrFail($id);
        $this->assertDraft($inv);
        if ($inv->detail->isEmpty()) {
            throw BizException::make('INV_EMPTY', 'Invoice tanpa baris tidak dapat di-match.');
        }

        foreach ($inv->detail as $n => $line) {
            $grDet = $line->grDetail;
            if (! $grDet) {
                throw BizException::make('INV_GR', 'Baris #' . ($n + 1) . ': referensi GR tidak ditemukan.');
            }
            if ((int) optional($grDet->main)->ven_id !== (int) $inv->ven_id) {
                throw BizException::make('INV_VENDOR', 'Baris #' . ($n + 1) . ': penerimaan bukan milik vendor invoice ini.');
            }
            $othersQty = (int) prc_inv_detail::where('gr_detail_id', $grDet->id)
                ->where('main_id', '!=', $inv->id)
                ->whereIn('main_id', prc_inv_main::whereIn('status', ['MATCHED', 'POSTED', 'PAID'])->pluck('id'))
                ->sum('qty');
            if ((int) $line->qty + $othersQty > (int) $grDet->qty) {
                throw BizException::make('INV_OVER', 'Baris #' . ($n + 1) . ": qty invoice ({$line->qty}) + invoice lain ({$othersQty}) melebihi qty GR ({$grDet->qty}).");
            }
        }

        $inv->update(['status' => 'MATCHED']);
        AuditLogger::record($request, "Match AP Invoice {$inv->code}", $inv->code);

        return ApiResponse::item($inv->load($this->with));
    }

    public function post(Request $request, int $id)
    {
        $inv = prc_inv_main::findOrFail($id);
        if ($inv->status !== 'MATCHED') {
            throw BizException::make('INV_BAD_STATE', 'Hanya invoice MATCHED yang dapat di-post.');
        }
        $inv->update(['status' => 'POSTED']);
        AuditLogger::record($request, "Post AP Invoice {$inv->code}", $inv->code);

        return ApiResponse::item($inv->load($this->with));
    }

    private function header(array $data): array
    {
        $dueDate = $data['due_date'] ?? null;
        if (! $dueDate && ! empty($data['po_id'])) {
            $po = prc_po_main::find($data['po_id']);
            if ($po) {
                $dueDate = date('Y-m-d', strtotime($data['date'] . ' + ' . ((int) ($po->top_days ?? 30)) . ' days'));
            }
        }

        return [
            'date' => $data['date'],
            'ven_id' => $data['ven_id'],
            'po_id' => $data['po_id'] ?? null,
            'inv_no' => $data['inv_no'],
            'tax_inv_no' => $data['tax_inv_no'] ?? null,
            'tax_inv_date' => $data['tax_inv_date'] ?? null,
            'due_date' => $dueDate,
        ];
    }

    private function syncLines(prc_inv_main $inv, array $lines): void
    {
        foreach ($lines as $l) {
            $qty = (int) $l['qty'];
            $price = (float) $l['price'];
            $inv->detail()->create([
                'gr_detail_id' => $l['gr_detail_id'],
                'qty' => $qty,
                'price' => $price,
                'amount' => round($qty * $price, 2),
            ]);
        }
    }

    /** dpp = Σ amount; vat/wht23 manual; total = dpp + vat − wht23. */
    private function recalcTotals(prc_inv_main $inv, array $data): void
    {
        $dpp = (float) $inv->detail()->sum('amount');
        $vat = (float) ($data['vat'] ?? 0);
        $wht = (float) ($data['wht23'] ?? 0);
        $inv->update([
            'dpp' => round($dpp, 2),
            'vat' => round($vat, 2),
            'wht23' => round($wht, 2),
            'total' => round($dpp + $vat - $wht, 2),
        ]);
    }

    private function assertDraft(prc_inv_main $inv): void
    {
        if ($inv->status !== 'DRAFT') {
            throw BizException::make('INV_LOCKED', 'Invoice yang sudah MATCHED/POSTED tidak dapat diubah.');
        }
    }

    private function validateInvoice(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'ven_id' => ['required', 'integer', 'exists:m_contacts,id'],
            'po_id' => ['nullable', 'integer', 'exists:prc_po_main,id'],
            'inv_no' => ['required', 'string', 'max:50'],
            'vat' => ['nullable', 'numeric', 'min:0'],
            'wht23' => ['nullable', 'numeric', 'min:0'],
            'tax_inv_no' => ['nullable', 'string', 'max:30'],
            'tax_inv_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'lines' => ['array'],
            'lines.*.gr_detail_id' => ['required', 'integer', 'exists:prc_gr_detail,id'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.price' => ['required', 'numeric', 'min:0'],
        ]);
    }
}
