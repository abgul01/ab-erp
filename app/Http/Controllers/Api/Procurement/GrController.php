<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\prc_gr_main;
use App\Models\prc_po_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use App\Support\QuotaService;
use App\Support\SerialService;
use App\Support\UomConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Goods Receipt (Fase 2C). One GR may receive MULTIPLE POs of the same vendor:
 * each line carries its po_id (prc_gr_detail.po_id); the header po_no stores the
 * joined PO codes for display. Per-bar serials (prc_gr_serial) carry millsheet +
 * dual UoM (PCS + KG). Posting bumps each PO's qty_received, books import-quota
 * actual tonnage per quota, and advances each PO's status. Edit reverses the old
 * receipt then re-applies; destroy reverses all effects.
 */
class GrController extends Controller
{
    private array $with = ['ven', 'user', 'detail.item', 'detail.quota', 'detail.po', 'detail.serials'];

    public function index(Request $request)
    {
        $query = prc_gr_main::with(['ven'])->withCount('detail');

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(fn ($s) => $s->where('code', 'like', "%{$q}%")->orWhere('po_no', 'like', "%{$q}%"));
        }
        if ($poNo = $request->query('po_no')) {
            $query->where('po_no', 'like', "%{$poNo}%");
        }
        if ($venId = $request->query('ven_id')) {
            $query->where('ven_id', $venId);
        }
        if ($request->boolean('with_detail')) {
            $query->with('detail.item', 'detail.po');
        }
        // Hanya GR yang masih punya baris belum tertagih penuh (untuk AP Invoice).
        if ($request->boolean('invoice_pending')) {
            $excludeInv = (int) $request->query('exclude_inv_id', 0);
            $query->whereExists(function ($q) use ($excludeInv) {
                $q->select(DB::raw(1))->from('prc_gr_detail as gd')
                    ->whereColumn('gd.id_prim', 'prc_gr_main.id')
                    ->whereRaw('gd.qty > (
                        SELECT COALESCE(SUM(pid.qty), 0) FROM prc_inv_detail pid
                        JOIN prc_inv_main pim ON pim.id = pid.main_id
                        WHERE pid.gr_detail_id = gd.id
                          AND pim.status IN ("MATCHED", "POSTED", "PAID")
                          AND pim.id <> ?
                    )', [$excludeInv]);
            });
        }
        // Hanya GR yang masih punya serial OK belum masuk Incoming (untuk WMS).
        if ($request->boolean('incoming_pending')) {
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))->from('prc_gr_serial as s')
                    ->join('prc_gr_detail as d', 'd.id', '=', 's.det_id')
                    ->whereColumn('d.id_prim', 'prc_gr_main.id')
                    ->where(fn ($w) => $w->whereNull('s.status')->orWhere('s.status', '!=', 'NG'))
                    ->whereNotExists(function ($q2) {
                        $q2->select(DB::raw(1))->from('wh_inc_detail as wd')
                            ->whereRaw('CONVERT(wd.serial_id USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(s.serial_id USING utf8mb4) COLLATE utf8mb4_general_ci');
                    });
            });
        }

        $query->orderByDesc('id');
        $perPage = min(max((int) $request->query('per_page', 20), 1), 200);

        return ApiResponse::paginated($query->paginate($perPage));
    }

    public function show(int $id)
    {
        return ApiResponse::item(prc_gr_main::with($this->with)->findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $this->validateGr($request);
        $pos = $this->loadPos($data, requireOpen: true);

        $gr = DB::transaction(function () use ($data, $pos, $request) {
            $gr = prc_gr_main::create([
                'code' => (new NumberingService)->next('GR', 'GR'),
                'ven_id' => $pos->first()->ven_id,
                'date' => $data['date'],
                'user_id' => $request->user()->id,
                'po_no' => $pos->pluck('code')->implode(','),
                'import_doc_no' => $data['import_doc_no'] ?? null,
                'status' => 'POSTED',
            ]);
            $this->applyReceipt($gr, $pos, $data['lines'], $request->user()->id);
            AuditLogger::record($request, "Create GR {$gr->code} ({$gr->po_no})", $gr->code);

            return $gr;
        });

        return ApiResponse::item($gr->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $gr = prc_gr_main::with('detail')->findOrFail($id);
        $data = $this->validateGr($request);
        $pos = $this->loadPos($data, requireOpen: false);

        if ($pos->pluck('ven_id')->unique()->count() > 1 || (int) $pos->first()->ven_id !== (int) $gr->ven_id) {
            throw BizException::make('GR_VENDOR', 'Semua PO pada GR harus milik vendor yang sama dengan GR.');
        }

        DB::transaction(function () use ($gr, $pos, $data, $request) {
            $this->reverseReceipt($gr, $request->user()->id);
            $gr->update([
                'date' => $data['date'],
                'po_no' => $pos->pluck('code')->implode(','),
                'import_doc_no' => $data['import_doc_no'] ?? null,
            ]);
            $this->applyReceipt($gr, $pos, $data['lines'], $request->user()->id);
            AuditLogger::record($request, "Update GR {$gr->code}", $gr->code);
        });

        return ApiResponse::item($gr->fresh()->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $gr = prc_gr_main::with('detail')->findOrFail($id);

        DB::transaction(function () use ($gr, $request) {
            $this->reverseReceipt($gr, $request->user()->id);
            $gr->delete();
            AuditLogger::record($request, "Delete GR {$gr->code}", $gr->code);
        });

        return ApiResponse::item(['message' => 'GR berhasil dihapus & efeknya dibatalkan.']);
    }

    /** Load + validate the POs referenced by the payload lines. */
    private function loadPos(array $data, bool $requireOpen)
    {
        $poIds = collect($data['lines'])->pluck('po_id')->unique()->values();
        $pos = prc_po_main::with('detail', 'quota')->whereIn('id', $poIds)->get()->keyBy('id');

        if ($pos->count() !== $poIds->count()) {
            throw BizException::make('GR_PO', 'Sebagian PO pada baris tidak ditemukan.');
        }
        if ($pos->pluck('ven_id')->unique()->count() > 1) {
            throw BizException::make('GR_VENDOR', 'Semua PO dalam satu GR harus dari vendor yang sama.');
        }
        if ($requireOpen) {
            foreach ($pos as $po) {
                if (! in_array($po->status, ['OPEN', 'INPROGRESS'], true)) {
                    throw BizException::make('PO_NOT_OPEN', "PO {$po->code} tidak berstatus Open / In Progress.");
                }
            }
        }

        return $pos;
    }

    /** Create detail+serials per line's PO, bump qty_received, book quota, sync statuses. */
    private function applyReceipt(prc_gr_main $gr, $pos, array $lines, ?int $userId): void
    {
        $serialSvc = app(SerialService::class);
        $uom = app(UomConversionService::class);
        $tonByQuota = [];

        foreach ($lines as $line) {
            $po = $pos[$line['po_id']];
            $quotaControlled = $po->source === 'IMPORT' && $po->po_type === 'RM' && $po->quota_id;

            $serials = $line['serials'] ?? [];
            $qty = array_sum(array_map(fn ($s) => (int) $s['qty'], $serials));
            $wTotal = array_sum(array_map(fn ($s) => (float) ($s['weight'] ?? 0), $serials));
            $unitW = $uom->unitWeight($wTotal, $qty);

            $det = $gr->detail()->create([
                'po_id' => $po->id,
                'item_id' => $line['item_id'],
                'quota_id' => $line['quota_id'] ?? ($quotaControlled ? $po->quota_id : null),
                'hs_code' => $line['hs_code'] ?? ($quotaControlled ? optional($po->quota)->hs_code : null),
                'qty' => $qty,
                'length' => $line['length'] ?? ($serials[0]['length'] ?? null),
                'weight' => $unitW,
                'w_total' => $wTotal,
                'note' => $line['note'] ?? null,
            ]);

            $serialSvc->generateForGrLine($det, $serials);

            self::bumpReceived($po, (int) $line['item_id'], $qty);
            if ($quotaControlled && $wTotal > 0) {
                $tonByQuota[$po->quota_id] = ($tonByQuota[$po->quota_id] ?? 0) + $uom->kgToTon($wTotal);
            }
        }

        $qSvc = new QuotaService;
        foreach ($tonByQuota as $quotaId => $ton) {
            $qSvc->bookActual($quotaId, $gr->id, round($ton, 3), $userId);
        }
        foreach ($pos as $po) {
            PoController::syncReceivingStatus($po, $userId);
        }
    }

    /** Undo a receipt's effects using each detail's own po_id. */
    private function reverseReceipt(prc_gr_main $gr, ?int $userId): void
    {
        $poIds = $gr->detail->pluck('po_id')->filter()->unique()->values();
        $pos = prc_po_main::with('detail')->whereIn('id', $poIds)->get()->keyBy('id');

        foreach ($gr->detail as $det) {
            $po = $det->po_id ? ($pos[$det->po_id] ?? null) : null;
            if ($po) {
                self::bumpReceived($po, (int) $det->item_id, -(int) $det->qty);
            }
        }
        (new QuotaService)->reverseActual($gr->id);
        $gr->detail()->each(fn ($d) => $d->serials()->delete());
        $gr->detail()->delete();
        $gr->load('detail');

        foreach ($pos as $po) {
            PoController::syncReceivingStatus($po, $userId);
        }
    }

    /** Distribute a received (or reversed, when negative) qty across matching PO lines. */
    public static function bumpReceived(prc_po_main $po, int $itemId, int $qty): void
    {
        if ($qty === 0) {
            return;
        }
        $lines = $po->detail->where('item_id', $itemId)->sortBy('id')->values();

        if ($qty > 0) {
            $remaining = $qty;
            foreach ($lines as $line) {
                if ($remaining <= 0) {
                    break;
                }
                $capacity = max(0, (int) $line->qty - (int) $line->qty_received);
                $take = min($capacity, $remaining);
                if ($take > 0) {
                    $line->increment('qty_received', $take);
                    $remaining -= $take;
                }
            }
            if ($remaining > 0 && $lines->isNotEmpty()) {
                $lines->last()->increment('qty_received', $remaining);
            }
        } else {
            $remaining = -$qty;
            foreach ($lines->reverse() as $line) {
                if ($remaining <= 0) {
                    break;
                }
                $take = min((int) $line->qty_received, $remaining);
                if ($take > 0) {
                    $line->decrement('qty_received', $take);
                    $remaining -= $take;
                }
            }
        }
    }

    private function validateGr(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'import_doc_no' => ['nullable', 'string', 'max:50'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.po_id' => ['required', 'integer', 'exists:prc_po_main,id'],
            'lines.*.item_id' => ['required', 'integer', 'exists:m_item,id'],
            'lines.*.length' => ['nullable', 'integer', 'min:0'],
            'lines.*.hs_code' => ['nullable', 'string', 'max:20'],
            'lines.*.quota_id' => ['nullable', 'integer', 'exists:m_quota,id'],
            'lines.*.note' => ['nullable', 'string', 'max:100'],
            'lines.*.serials' => ['required', 'array', 'min:1'],
            'lines.*.serials.*.serial_id' => ['required', 'string', 'max:50'],
            'lines.*.serials.*.millsheet' => ['nullable', 'string', 'max:50'],
            'lines.*.serials.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.serials.*.length' => ['nullable', 'integer', 'min:0'],
            'lines.*.serials.*.weight' => ['nullable', 'numeric', 'min:0'],
            'lines.*.serials.*.status' => ['nullable', 'in:OK,NG'],
            'lines.*.serials.*.ng_reason' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
