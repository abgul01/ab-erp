<?php

namespace App\Http\Controllers\Api\Whs;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\whs_po_main;
use App\Support\ApiResponse;
use App\Support\ApprovalEngine;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Purchase Order gudang WHS.
 *
 * Terpisah dari PO produksi karena isinya lain sama sekali: tidak ada kuota
 * impor, tidak ada berat per batang, tidak ada landed cost per kilogram. Yang
 * dibeli di sini adalah mata bor dan sarung tangan, dan menumpangkannya pada
 * PO bahan baku hanya akan membuat kedua layar penuh kolom yang tidak relevan.
 */
class WhsPoController extends Controller
{
    private array $with = ['detail.item', 'ven'];

    public function index(Request $request)
    {
        $rows = whs_po_main::with(['ven'])->withCount('detail')
            ->when($request->query('q'), fn ($q, $s) => $q->where('code', 'like', "%{$s}%"))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        return ApiResponse::item(whs_po_main::with($this->with)->findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $this->validatePo($request);

        $po = DB::transaction(function () use ($data, $request) {
            $po = whs_po_main::create([
                'code' => app(NumberingService::class)->next('WHSPO', 'WPO'),
                'date' => $data['date'],
                'ven_id' => $data['ven_id'],
                'currency_id' => $data['currency_id'] ?? null,
                'rate' => $data['rate'] ?? 1,
                'top_days' => $data['top_days'] ?? 0,
                'eta' => $data['eta'] ?? null,
                'note' => $data['note'] ?? null,
                'user_id' => $request->user()->id,
                'status' => 'DRAFT',
            ]);
            $this->syncLines($po, $data['lines']);
            AuditLogger::record($request, "Create PO WHS {$po->code}", $po->code);

            return $po;
        });

        return ApiResponse::item($po->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $po = whs_po_main::findOrFail($id);
        $this->assertDraft($po);
        $data = $this->validatePo($request);

        DB::transaction(function () use ($po, $data, $request) {
            $po->update([
                'date' => $data['date'],
                'ven_id' => $data['ven_id'],
                'currency_id' => $data['currency_id'] ?? null,
                'rate' => $data['rate'] ?? 1,
                'top_days' => $data['top_days'] ?? 0,
                'eta' => $data['eta'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
            $po->detail()->delete();
            $this->syncLines($po, $data['lines']);
            AuditLogger::record($request, "Update PO WHS {$po->code}", $po->code);
        });

        return ApiResponse::item($po->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $po = whs_po_main::findOrFail($id);
        $this->assertDraft($po);

        DB::transaction(function () use ($po, $request) {
            $po->detail()->delete();
            $po->delete();
            AuditLogger::record($request, "Delete PO WHS {$po->code}", $po->code);
        });

        return ApiResponse::item(['message' => 'PO WHS dihapus.']);
    }

    public function submit(Request $request, int $id)
    {
        $po = whs_po_main::with('detail')->findOrFail($id);

        if ($po->status !== 'DRAFT') {
            throw BizException::make('WHSPO_NOT_DRAFT', 'Hanya PO WHS berstatus DRAFT yang dapat disubmit.');
        }
        if ($po->detail->isEmpty()) {
            throw BizException::make('WHSPO_EMPTY', 'PO WHS tanpa baris barang tidak dapat disubmit.');
        }

        $po->submitForApproval();
        AuditLogger::record($request, "Submit PO WHS {$po->code}", $po->code);

        return ApiResponse::item($po->fresh()->load($this->with));
    }

    public function approve(Request $request, int $id)
    {
        $po = whs_po_main::findOrFail($id);

        if ($po->status !== 'SUBMITTED') {
            throw BizException::make('WHSPO_BAD_STATE', 'PO WHS harus berstatus SUBMITTED untuk di-approve.');
        }

        app(ApprovalEngine::class)->approve($po, $request->user(), $request->input('note'));

        return ApiResponse::item($po->fresh()->load($this->with));
    }

    public function reject(Request $request, int $id)
    {
        $po = whs_po_main::findOrFail($id);
        $request->validate(['note' => 'required|string|max:300']);

        app(ApprovalEngine::class)->reject($po, $request->user(), $request->input('note'));

        return ApiResponse::item($po->fresh()->load($this->with));
    }

    /** Tutup manual: sisa pesanan dibatalkan karena pemasok tidak mengirim lagi. */
    public function close(Request $request, int $id)
    {
        $po = whs_po_main::findOrFail($id);

        if (! in_array($po->status, ['APPROVED', 'SUBMITTED'], true)) {
            throw BizException::make('WHSPO_STATE', 'Hanya PO berjalan yang dapat ditutup.');
        }

        $po->update(['status' => 'CLOSE']);
        AuditLogger::record($request, "Tutup PO WHS {$po->code}", $po->code);

        return ApiResponse::item($po->load($this->with));
    }

    /** Baris PO yang masih kurang terima — dipakai layar penerimaan. */
    public function openLines(int $id)
    {
        $po = whs_po_main::with('detail.item')->findOrFail($id);

        return ApiResponse::item([
            'po_id' => $po->id,
            'code' => $po->code,
            'ven_id' => $po->ven_id,
            'status' => $po->status,
            'lines' => $po->detail
                ->map(fn ($d) => [
                    'po_det_id' => $d->id,
                    'item_id' => $d->item_id,
                    'code' => $d->item?->code,
                    'name' => $d->item?->name,
                    'whs_type' => $d->item?->whs_type,
                    'qty' => (int) $d->qty,
                    'qty_received' => (int) $d->qty_received,
                    'outstanding' => max(0, (int) $d->qty - (int) $d->qty_received),
                    'price' => (float) $d->price,
                ])
                ->filter(fn ($l) => $l['outstanding'] > 0)
                ->values(),
        ]);
    }

    /* ---------------- helpers ---------------- */

    private function assertDraft(whs_po_main $po): void
    {
        if ($po->status !== 'DRAFT') {
            throw BizException::make('WHSPO_LOCKED', 'PO WHS yang sudah disubmit tidak dapat diubah.');
        }
    }

    private function syncLines(whs_po_main $po, array $lines): void
    {
        foreach ($lines as $l) {
            $po->detail()->create([
                'item_id' => $l['item_id'],
                'qty' => $l['qty'],
                'price' => $l['price'] ?? 0,
                'note' => $l['note'] ?? null,
            ]);
        }
    }

    private function validatePo(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'ven_id' => ['required', 'integer', 'exists:m_contacts,id'],
            'currency_id' => ['nullable', 'integer', 'exists:m_currency,id'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'top_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'eta' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:300'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:m_whs_item,id'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.note' => ['nullable', 'string', 'max:150'],
        ]);
    }
}
