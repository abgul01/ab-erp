<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\m_quota_item;
use App\Models\prc_po_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use App\Support\QuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Purchase Order (Fase 2B). Header prc_po_main + lines prc_po_detail, optionally
 * pulled from approved PR lines. Import RM POs reserve import quota on approval.
 * Lifecycle: DRAFT → APPROVED → CLOSED (or CANCELLED). Editable only in DRAFT.
 */
class PoController extends Controller
{
    private array $with = ['ven', 'quota', 'currency', 'user', 'detail.item', 'detail.uom', 'detail.prDetail'];
    private const PO_TYPES = ['RM', 'GENERAL', 'NPD', 'SERVICE', 'SUBCONT', 'ASSET'];
    private const SOURCES = ['LOCAL', 'IMPORT'];

    public function index(Request $request)
    {
        $query = prc_po_main::with(['ven', 'currency'])->withCount('detail');

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where('code', 'like', "%{$q}%")
                ->orWhereHas('ven', fn ($s) => $s->where('company_n', 'like', "%{$q}%"));
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $query->orderByDesc('id');
        $perPage = min(max((int) $request->query('per_page', 20), 1), 200);

        return ApiResponse::paginated($query->paginate($perPage));
    }

    public function show(int $id)
    {
        return ApiResponse::item(prc_po_main::with($this->with)->findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $this->validatePo($request);
        $this->assertQuotaRules($data);

        $po = DB::transaction(function () use ($data, $request) {
            $po = prc_po_main::create([
                'code' => (new NumberingService)->next('PO', 'PO'),
                'date' => $data['date'],
                'po_type' => $data['po_type'],
                'source' => $data['source'],
                'ven_id' => $data['ven_id'],
                'quota_id' => $data['quota_id'] ?? null,
                'currency_id' => $data['currency_id'] ?? null,
                'rate' => $data['rate'] ?? 1,
                'top_days' => $data['top_days'] ?? 30,
                'eta' => $data['eta'] ?? null,
                'user_id' => $request->user()->id,
                'status' => 'DRAFT',
            ]);
            $this->syncLines($po, $data['lines'] ?? []);
            AuditLogger::record($request, "Create PO {$po->code}", $po->code);

            return $po;
        });

        return ApiResponse::item($po->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $po = prc_po_main::with('detail')->findOrFail($id);
        if ($po->status === 'CANCELLED') {
            throw BizException::make('PO_CANCELLED', 'PO yang sudah dibatalkan tidak dapat diubah.');
        }

        // After approval, the type/source/quota basis is locked (amend mode):
        // force the existing values so the client can't change them.
        $draft = $po->status === 'DRAFT';
        if (! $draft) {
            $request->merge(['po_type' => $po->po_type, 'source' => $po->source, 'quota_id' => $po->quota_id]);
        }
        $data = $this->validatePo($request);
        $this->assertQuotaRules($data);

        DB::transaction(function () use ($po, $data, $request, $draft) {
            $header = [
                'date' => $data['date'],
                'ven_id' => $data['ven_id'],
                'currency_id' => $data['currency_id'] ?? null,
                'rate' => $data['rate'] ?? 1,
                'top_days' => $data['top_days'] ?? 30,
                'eta' => $data['eta'] ?? null,
            ];
            if ($draft) {
                $header += ['po_type' => $data['po_type'], 'source' => $data['source'], 'quota_id' => $data['quota_id'] ?? null];
                $po->update($header);
                $po->detail()->delete();
                $this->syncLines($po, $data['lines'] ?? []);
            } else {
                $po->update($header);
                $this->amendLines($po, $data['lines'] ?? []);
                if ($this->isQuotaControlled($po)) {
                    $svc = new QuotaService;
                    $svc->releasePo($po->quota_id, $po->id, $request->user()->id);
                    $po->load('detail');
                    $newTon = round($po->detail->sum('est_weight') / 1000, 3);
                    if ($newTon > $svc->balance($po->quota_id)) {
                        throw BizException::make('QUOTA_EXCEEDED', 'Estimasi berat PO (setelah edit) melebihi sisa kuota impor.');
                    }
                    $svc->reservePo($po->quota_id, $po->id, $newTon, $request->user()->id);
                }
                self::syncReceivingStatus($po, $request->user()->id);
            }
            AuditLogger::record($request, "Update PO {$po->code}", $po->code);
        });

        return ApiResponse::item($po->fresh()->load($this->with));
    }

    /**
     * Amend an approved PO's lines in place (preserve qty_received / pr link):
     * update existing (by id), create new, delete only lines with no receipts.
     */
    private function amendLines(prc_po_main $po, array $lines): void
    {
        $existing = $po->detail->keyBy('id');
        $seen = [];

        foreach ($lines as $n => $l) {
            $id = $l['id'] ?? null;
            if ($id && $existing->has($id)) {
                $line = $existing[$id];
                $recv = (int) $line->qty_received;
                if ((int) $l['qty'] < $recv) {
                    throw BizException::make('PO_QTY_LT_RECV', 'Baris #' . ($n + 1) . ": qty ({$l['qty']}) tidak boleh lebih kecil dari qty yang sudah diterima ({$recv}).");
                }
                if ($recv > 0 && (int) $l['item_id'] !== (int) $line->item_id) {
                    throw BizException::make('PO_ITEM_LOCKED', 'Baris #' . ($n + 1) . ': item tidak dapat diganti karena sudah ada penerimaan.');
                }
                $line->update([
                    'item_id' => $l['item_id'],
                    'qty' => $l['qty'],
                    'uom_id' => $l['uom_id'] ?? $line->uom_id,
                    'price' => $l['price'] ?? 0,
                    'price_kg' => $l['price_kg'] ?? null,
                    'tax_id' => $l['tax_id'] ?? null,
                    ...$this->estFields($l),
                    'due_date' => $l['due_date'] ?? null,
                ]);
                $seen[] = (int) $id;
            } else {
                $new = $po->detail()->create([
                    'pr_detail_id' => $l['pr_detail_id'] ?? null,
                    'item_id' => $l['item_id'],
                    'qty' => $l['qty'],
                    'uom_id' => $l['uom_id'] ?? null,
                    'price' => $l['price'] ?? 0,
                    'price_kg' => $l['price_kg'] ?? null,
                    'tax_id' => $l['tax_id'] ?? null,
                    'est_weight' => $l['est_weight'] ?? null,
                    'est_length' => $l['est_length'] ?? null,
                    'due_date' => $l['due_date'] ?? null,
                    'qty_received' => 0,
                ]);
                $seen[] = (int) $new->id;
            }
        }

        foreach ($existing as $id => $line) {
            if (! in_array((int) $id, $seen, true)) {
                if ((int) $line->qty_received > 0) {
                    throw BizException::make('PO_LINE_HAS_RECV', "Baris item '{$line->item_id}' tidak dapat dihapus karena sudah ada penerimaan.");
                }
                $line->delete();
            }
        }
    }

    public function destroy(Request $request, int $id)
    {
        $po = prc_po_main::findOrFail($id);
        $this->assertDraft($po);
        DB::transaction(function () use ($po, $request) {
            $po->detail()->delete();
            $po->delete();
            AuditLogger::record($request, "Delete PO {$po->code}", $po->code);
        });

        return ApiResponse::item(['message' => 'PO berhasil dihapus.']);
    }

    public function approve(Request $request, int $id)
    {
        $po = prc_po_main::with('detail')->findOrFail($id);
        if ($po->status !== 'DRAFT') {
            throw BizException::make('PO_BAD_STATE', 'Hanya PO DRAFT yang dapat di-approve.');
        }
        if ($po->detail->isEmpty()) {
            throw BizException::make('PO_EMPTY', 'PO tanpa baris item tidak dapat di-approve.');
        }

        DB::transaction(function () use ($po, $request) {
            if ($this->isQuotaControlled($po)) {
                $quota = new QuotaService;
                $reserveTon = round($po->detail->sum('est_weight') / 1000, 3);
                if ($reserveTon > $quota->balance($po->quota_id)) {
                    throw BizException::make('QUOTA_EXCEEDED', 'Estimasi berat PO melebihi sisa kuota impor.');
                }
                $quota->reservePo($po->quota_id, $po->id, $reserveTon, $request->user()->id);
            }
            // Approved PO starts OPEN (belum ada kedatangan). Advances to
            // INPROGRESS / CLOSE automatically as goods are received (see GR).
            $po->update(['status' => 'OPEN']);
            AuditLogger::record($request, "Approve PO {$po->code}", $po->code);
        });

        return ApiResponse::item($po->load($this->with));
    }

    public function close(Request $request, int $id)
    {
        $po = prc_po_main::findOrFail($id);
        if (! in_array($po->status, ['OPEN', 'INPROGRESS'], true)) {
            throw BizException::make('PO_BAD_STATE', 'Hanya PO Open / In Progress yang dapat di-close.');
        }
        DB::transaction(function () use ($po, $request) {
            if ($this->isQuotaControlled($po)) {
                (new QuotaService)->releasePo($po->quota_id, $po->id, $request->user()->id);
            }
            $po->update(['status' => 'CLOSE']);
            AuditLogger::record($request, "Close PO {$po->code}", $po->code);
        });

        return ApiResponse::item($po->load($this->with));
    }

    public function cancel(Request $request, int $id)
    {
        $po = prc_po_main::findOrFail($id);
        if (! in_array($po->status, ['DRAFT', 'OPEN', 'INPROGRESS'], true)) {
            throw BizException::make('PO_BAD_STATE', 'PO ini tidak dapat dibatalkan.');
        }
        DB::transaction(function () use ($po, $request) {
            if ($this->isQuotaControlled($po)) {
                (new QuotaService)->releasePo($po->quota_id, $po->id, $request->user()->id);
            }
            $po->update(['status' => 'CANCELLED']);
            AuditLogger::record($request, "Cancel PO {$po->code}", $po->code);
        });

        return ApiResponse::item($po->load($this->with));
    }

    private function isQuotaControlled(prc_po_main $po): bool
    {
        return $po->source === 'IMPORT' && $po->po_type === 'RM' && $po->quota_id;
    }

    /**
     * Recompute a PO's receiving state from qty_received after a GR posts/reverses:
     *   OPEN (1) → INPROGRESS (2, partial) → CLOSE (3, fully received).
     * Releases the quota reserve on auto-close, re-reserves if a reversal reopens it.
     */
    public static function syncReceivingStatus(prc_po_main $po, ?int $userId): void
    {
        if (in_array($po->status, ['DRAFT', 'CANCELLED'], true)) {
            return;
        }
        $po->load('detail');
        $received = (int) $po->detail->sum('qty_received');
        $fully = $po->detail->isNotEmpty()
            && $po->detail->every(fn ($l) => (int) $l->qty_received >= (int) $l->qty);
        $new = $received <= 0 ? 'OPEN' : ($fully ? 'CLOSE' : 'INPROGRESS');

        if ($new === $po->status) {
            return;
        }
        if ($po->source === 'IMPORT' && $po->po_type === 'RM' && $po->quota_id) {
            $svc = new QuotaService;
            if ($new === 'CLOSE') {
                $svc->releasePo($po->quota_id, $po->id, $userId);
            } elseif ($po->status === 'CLOSE') {
                $svc->reservePo($po->quota_id, $po->id, round($po->detail->sum('est_weight') / 1000, 3), $userId);
            }
        }
        $po->update(['status' => $new]);
    }

    private function assertDraft(prc_po_main $po): void
    {
        if ($po->status !== 'DRAFT') {
            throw BizException::make('PO_LOCKED', 'PO yang sudah di-approve tidak dapat diubah.');
        }
    }

    private function assertQuotaRules(array $data): void
    {
        $importRm = ($data['source'] ?? null) === 'IMPORT' && ($data['po_type'] ?? null) === 'RM';
        if (! $importRm) {
            return;
        }
        if (empty($data['quota_id'])) {
            throw BizException::make('QUOTA_REQUIRED', 'PO impor RM wajib memilih kuota impor.');
        }
        $allowed = m_quota_item::where('quota_id', $data['quota_id'])->pluck('item_id')->all();
        foreach ($data['lines'] ?? [] as $i => $l) {
            if (! in_array((int) $l['item_id'], $allowed, true)) {
                throw BizException::make('QUOTA_ITEM', "Baris #" . ($i + 1) . ": item tidak terdaftar pada kuota terpilih.");
            }
            if (($this->estFields($l)['est_weight'] ?? 0) <= 0) {
                throw BizException::make('QUOTA_WEIGHT', "Baris #" . ($i + 1) . ": estimasi berat (kg/satuan) wajib diisi untuk PO impor.");
            }
        }
    }

    /** Per-unit estimates + derived totals (total = qty × per-unit). */
    private function estFields(array $l): array
    {
        $qty = (int) ($l['qty'] ?? 0);
        $wu = ($l['est_weight_unit'] ?? '') === '' ? null : (float) $l['est_weight_unit'];
        $lu = ($l['est_length_unit'] ?? '') === '' ? null : (float) $l['est_length_unit'];

        return [
            'est_weight_unit' => $wu,
            'est_length_unit' => $lu,
            'est_weight' => $wu !== null ? round($qty * $wu, 2) : ($l['est_weight'] ?? null),
            'est_length' => $lu !== null ? round($qty * $lu, 2) : ($l['est_length'] ?? null),
        ];
    }

    private function syncLines(prc_po_main $po, array $lines): void
    {
        foreach ($lines as $l) {
            $po->detail()->create([
                'pr_detail_id' => $l['pr_detail_id'] ?? null,
                'item_id' => $l['item_id'],
                'qty' => $l['qty'],
                'uom_id' => $l['uom_id'] ?? null,
                'price' => $l['price'] ?? 0,
                'price_kg' => $l['price_kg'] ?? null,
                'tax_id' => $l['tax_id'] ?? null,
                ...$this->estFields($l),
                'due_date' => $l['due_date'] ?? null,
                'qty_received' => 0,
            ]);
        }
    }

    private function validatePo(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'po_type' => ['required', Rule::in(self::PO_TYPES)],
            'source' => ['required', Rule::in(self::SOURCES)],
            'ven_id' => ['required', 'integer', 'exists:m_contacts,id'],
            'quota_id' => ['nullable', 'integer', 'exists:m_quota,id'],
            'currency_id' => ['nullable', 'integer', 'exists:m_currency,id'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'top_days' => ['nullable', 'integer', 'min:0'],
            'eta' => ['nullable', 'date'],
            'lines' => ['array'],
            'lines.*.id' => ['nullable', 'integer', 'exists:prc_po_detail,id'],
            'lines.*.pr_detail_id' => ['nullable', 'integer', 'exists:prc_pr_detail,id'],
            'lines.*.item_id' => ['required', 'integer', 'exists:m_item,id'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.uom_id' => ['nullable', 'integer', 'exists:m_uom,id'],
            'lines.*.price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.price_kg' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_id' => ['nullable', 'integer', 'exists:m_tax,id'],
            'lines.*.est_weight_unit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.est_length_unit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.est_weight' => ['nullable', 'numeric', 'min:0'],
            'lines.*.est_length' => ['nullable', 'numeric', 'min:0'],
            'lines.*.due_date' => ['nullable', 'date'],
        ]);
    }
}
