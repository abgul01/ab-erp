<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\sub_po_detail;
use App\Models\sub_po_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Subcontract Purchase Order — a document of its own (separate from the general
 * PO). Lines are restricted to what the vendor handles per the subcont master
 * (m_subcont_item). Lifecycle DRAFT → OPEN (approve) → CLOSED / CANCELLED; the
 * DN/GR flow works against OPEN subcont POs.
 */
class SubcontPoController extends Controller
{
    private array $with = ['ven', 'detail.item'];

    public function index(Request $request)
    {
        $rows = sub_po_main::with(['ven'])->withCount('detail')
            ->when($request->query('q'), fn ($q, $s) => $q->where('code', 'like', "%{$s}%")
                ->orWhereHas('ven', fn ($w) => $w->where('company_n', 'like', "%{$s}%")))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        return ApiResponse::item(sub_po_main::with($this->with)->findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $this->validatePo($request);
        $this->assertMasterItems($data);

        $po = DB::transaction(function () use ($data, $request) {
            $po = sub_po_main::create([
                'code' => (new NumberingService)->next('SUBPO', 'SPO'),
                'date' => $data['date'], 'ven_id' => $data['ven_id'],
                'user_id' => $request->user()->id, 'status' => 'DRAFT', 'note' => $data['note'] ?? null,
            ]);
            $this->syncLines($po, $data['lines']);
            AuditLogger::record($request, "Create Subcont PO {$po->code}", $po->code);

            return $po;
        });

        return ApiResponse::item($po->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $po = sub_po_main::findOrFail($id);
        $this->assertDraft($po);
        $data = $this->validatePo($request);
        $this->assertMasterItems($data);

        DB::transaction(function () use ($po, $data, $request) {
            $po->update(['date' => $data['date'], 'ven_id' => $data['ven_id'], 'note' => $data['note'] ?? null]);
            $po->detail()->delete();
            $this->syncLines($po, $data['lines']);
            AuditLogger::record($request, "Update Subcont PO {$po->code}", $po->code);
        });

        return ApiResponse::item($po->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $po = sub_po_main::findOrFail($id);
        $this->assertDraft($po);
        DB::transaction(function () use ($po, $request) {
            $po->detail()->delete();
            $po->delete();
            AuditLogger::record($request, "Delete Subcont PO {$po->code}", $po->code);
        });

        return ApiResponse::item(['message' => 'Subcont PO dihapus.']);
    }

    public function approve(Request $request, int $id)
    {
        $po = sub_po_main::with('detail')->findOrFail($id);
        if ($po->status !== 'DRAFT') {
            throw BizException::make('SPO_STATE', 'PO subcont harus DRAFT untuk di-approve.');
        }
        if ($po->detail->isEmpty()) {
            throw BizException::make('SPO_EMPTY', 'PO tanpa baris tidak dapat di-approve.');
        }
        $po->update(['status' => 'OPEN']);
        AuditLogger::record($request, "Approve Subcont PO {$po->code}", $po->code);

        return ApiResponse::item($po->load($this->with));
    }

    public function close(Request $request, int $id)
    {
        $po = sub_po_main::findOrFail($id);
        if (! in_array($po->status, ['OPEN', 'DRAFT'], true)) {
            throw BizException::make('SPO_STATE', 'PO ini tidak dapat ditutup.');
        }
        $po->update(['status' => $po->status === 'DRAFT' ? 'CANCELLED' : 'CLOSED']);
        AuditLogger::record($request, "Close/Cancel Subcont PO {$po->code}", $po->code);

        return ApiResponse::item($po->load($this->with));
    }

    /* ---------------- helpers ---------------- */

    private function assertDraft(sub_po_main $po): void
    {
        if ($po->status !== 'DRAFT') {
            throw BizException::make('SPO_LOCKED', 'PO yang sudah di-approve tidak dapat diubah.');
        }
    }

    /** Every line's item must be registered to the vendor in the subcont master. */
    private function assertMasterItems(array $data): void
    {
        $allowed = DB::table('m_subcont_item')->where('ven_id', $data['ven_id'])->where('active', 1)
            ->pluck('item_id')->all();
        foreach ($data['lines'] as $i => $l) {
            if (! in_array((int) $l['item_id'], $allowed, true)) {
                throw BizException::make('SPO_ITEM', 'Baris #'.($i + 1).': item belum terdaftar untuk vendor ini di Master Subcont.');
            }
        }
    }

    private function syncLines(sub_po_main $po, array $lines): void
    {
        foreach ($lines as $l) {
            sub_po_detail::create([
                'main_id' => $po->id, 'item_id' => $l['item_id'],
                'process_id' => $l['process_id'] ?? null, 'qty' => $l['qty'], 'price' => $l['price'] ?? 0,
            ]);
        }
    }

    private function validatePo(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'ven_id' => ['required', 'integer', 'exists:m_contacts,id'],
            'note' => ['nullable', 'string', 'max:200'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:m_item,id'],
            'lines.*.process_id' => ['nullable', 'integer', 'exists:m_process,id'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.price' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
