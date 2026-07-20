<?php

namespace App\Http\Controllers\Api\Production;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\m_bom;
use App\Models\m_item;
use App\Models\prd_mps;
use App\Models\prd_wo_main;
use App\Models\prd_wo_serial_rm;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Work Order (Fase 3 MES). Assembled in one workspace like the reference app:
 * header (FG + qty + customer + SO + without-cut / for-pm) then RM lines with
 * per-serial booking (prd_wo_detail_rm + prd_wo_serial_rm) and Assy/PM lines
 * (prd_wo_detail_pm + prd_wo_serial_pm), all from the FG's BOM.
 *
 * status (int): 1 DRAFT · 2 RELEASED · 3 CLOSED · 9 CANCELLED.
 */
class WoController extends Controller
{
    public const DRAFT = 1;
    public const RELEASED = 2;
    public const CLOSED = 3;
    public const CANCELLED = 9;

    public function index(Request $request)
    {
        $query = prd_wo_main::with(['fg', 'customer'])->withCount(['detailRm', 'detailPm']);
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where('code', 'like', "%{$q}%")
                ->orWhereHas('fg', fn ($s) => $s->where('code', 'like', "%{$q}%")->orWhere('part_name', 'like', "%{$q}%"));
        }
        if (($st = $request->query('status')) !== null && $st !== '') {
            $query->where('status', (int) $st);
        }
        if ($request->boolean('open')) {
            $query->whereIn('status', [self::DRAFT, self::RELEASED]);
        }
        $query->orderByDesc('id');

        return ApiResponse::paginated($query->paginate(min(max((int) $request->query('per_page', 20), 1), 200)));
    }

    public function show(int $id)
    {
        $wo = prd_wo_main::with(['fg', 'customer', 'user', 'mps', 'detailRm.rm', 'detailRm.serials', 'detailPm.pm', 'detailPm.serials'])->findOrFail($id);

        return ApiResponse::item($this->present($wo));
    }

    /** FG dimensions + its BOM (RM & PM) — feeds the header spec row and BOM pickers. */
    public function fgInfo(Request $request, int $fgId)
    {
        $fg = m_item::findOrFail($fgId);
        $qty = max(1, (int) $request->query('qty', 1));
        $bom = m_bom::with(['rmLines.material', 'pmLines.part'])->where('item_id', $fgId)->first();

        // Smallest cut length any FG's BOM requires from each material — if a
        // serial's leftover is below this, it can't feed any FG → scrap.
        $matIds = $bom ? $bom->rmLines->pluck('mat_id')->all() : [];
        $minUse = DB::table('m_bom_det_rm')->whereIn('mat_id', $matIds ?: [0])
            ->selectRaw('mat_id, MIN(length_use) as m')->groupBy('mat_id')->pluck('m', 'mat_id');

        $rm = $bom ? $bom->rmLines->map(fn ($l) => [
            'rm_id' => $l->mat_id,
            'code' => $l->material?->code,
            'spec' => $this->spec($l->material),
            'length_use' => (float) $l->length_use,
            'length_cut' => (float) $l->length_cut,
            'priority' => (int) $l->priority,
            'min_length' => (float) $l->length_use,
            'min_use_all' => (float) ($minUse[$l->mat_id] ?? $l->length_use),
        ])->values()->all() : [];

        $pm = $bom ? $bom->pmLines->map(fn ($l) => [
            'pm_id' => $l->pm_id,
            'code' => $l->part?->code,
            'spec' => $this->spec($l->part),
            'per_fg' => (int) $l->qty,
            'need' => $qty * (int) $l->qty,
        ])->values()->all() : [];

        return ApiResponse::item([
            'fg' => $fg->only(['id', 'code', 'part_name', 'o_d', 'i_d', 'thick', 'width', 'height', 'length', 'length_cut']),
            'length_cut' => (float) ($fg->length_cut ?? 0),
            'length_req' => $rm ? $rm[0]['length_use'] : 0,
            'has_bom' => (bool) $bom && (count($rm) || count($pm)),
            'bom_rm' => $rm,
            'bom_pm' => $pm,
        ]);
    }

    /** On-hand serials for a material, excluding those already booked to an open WO. */
    public function availableSerials(Request $request)
    {
        $itemId = (int) $request->query('item_id');
        if (! $itemId) {
            return ApiResponse::collection([]);
        }
        $excludeWo = (int) $request->query('exclude_wo', 0);

        $rows = DB::table('wh_inc_detail as wd')
            ->leftJoin('prc_gr_serial as gs', 'gs.serial_id', '=', 'wd.serial_id')
            ->leftJoin('m_rack as r', 'r.id', '=', 'wd.rack_id')
            ->where('wd.item_id', $itemId)
            ->whereNotIn('wd.serial_id', DB::table('wh_out_detail')->select('serial_id'))
            ->whereNotExists(function ($q) use ($excludeWo) {
                $q->select(DB::raw(1))->from('prd_wo_serial_rm as ws')
                    ->join('prd_wo_detail_rm as wd2', 'wd2.id', '=', 'ws.detail_id')
                    ->join('prd_wo_main as wm', 'wm.id', '=', 'wd2.main_id')
                    ->whereIn('wm.status', [self::DRAFT, self::RELEASED])
                    ->where('wm.id', '<>', $excludeWo)
                    ->whereRaw('CONVERT(ws.serial_id USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(wd.serial_id USING utf8mb4) COLLATE utf8mb4_general_ci');
            })
            ->orderBy('wd.serial_id')
            ->get([
                DB::raw('CONVERT(wd.serial_id USING utf8mb4) COLLATE utf8mb4_general_ci as serial_id'),
                DB::raw('CONVERT(gs.millsheet USING utf8mb4) COLLATE utf8mb4_general_ci as millsheet'),
                'wd.length', 'wd.qty',
            ]);

        return ApiResponse::collection($rows);
    }

    public function store(Request $request)
    {
        $data = $this->validateWo($request);
        $this->assertMps($data, null);
        [$rmIds, $pmIds] = $this->bomIds($data['fg_id']);
        $this->assertLines($data, $rmIds, $pmIds, 0);

        $wo = DB::transaction(function () use ($data, $request) {
            $wo = prd_wo_main::create([
                'code' => (new NumberingService)->next('WO', 'WO'),
                'date' => $data['date'],
                'customer_id' => $data['customer_id'],
                'so_id' => ($data['so_id'] ?? '') ?: '-',
                'fg_id' => $data['fg_id'],
                'mps_id' => $data['mps_id'],
                'user_id' => $request->user()->id,
                'qty' => $data['qty'],
                'status' => self::DRAFT,
                'no_cut' => (int) ($data['no_cut'] ?? 0),
                'for_pm' => (int) ($data['for_pm'] ?? 0),
            ]);
            $this->syncLines($wo, $data);
            AuditLogger::record($request, "Create WO {$wo->code} (FG#{$data['fg_id']} x{$data['qty']})", $wo->code);

            return $wo;
        });

        return $this->show($wo->id)->setStatusCode(201);
    }

    public function update(Request $request, int $id)
    {
        $wo = prd_wo_main::findOrFail($id);
        $this->assertDraft($wo);
        $data = $this->validateWo($request);
        $this->assertMps($data, $wo->id);
        [$rmIds, $pmIds] = $this->bomIds($data['fg_id']);
        $this->assertLines($data, $rmIds, $pmIds, $wo->id);

        DB::transaction(function () use ($wo, $data, $request) {
            $wo->update([
                'date' => $data['date'],
                'customer_id' => $data['customer_id'],
                'so_id' => ($data['so_id'] ?? '') ?: '-',
                'fg_id' => $data['fg_id'],
                'mps_id' => $data['mps_id'],
                'qty' => $data['qty'],
                'no_cut' => (int) ($data['no_cut'] ?? 0),
                'for_pm' => (int) ($data['for_pm'] ?? 0),
            ]);
            $wo->detailRm()->delete();
            $wo->detailPm()->delete();
            $this->syncLines($wo, $data);
            AuditLogger::record($request, "Update WO {$wo->code}", $wo->code);
        });

        return $this->show($id);
    }

    public function destroy(Request $request, int $id)
    {
        $wo = prd_wo_main::findOrFail($id);
        $this->assertDraft($wo);
        DB::transaction(function () use ($wo, $request) {
            $wo->detailRm()->delete();
            $wo->detailPm()->delete();
            $wo->delete();
            AuditLogger::record($request, "Delete WO {$wo->code}", $wo->code);
        });

        return ApiResponse::item(['message' => 'WO berhasil dihapus.']);
    }

    public function release(Request $request, int $id)
    {
        $wo = prd_wo_main::with('detailRm', 'detailPm')->findOrFail($id);
        if ((int) $wo->status !== self::DRAFT) {
            throw BizException::make('WO_STATE', 'Hanya WO DRAFT yang dapat di-release.');
        }
        if ($wo->detailRm->isEmpty() && $wo->detailPm->isEmpty()) {
            throw BizException::make('WO_EMPTY', 'WO tanpa baris RM/PM tidak dapat di-release.');
        }
        $wo->update(['status' => self::RELEASED]);
        AuditLogger::record($request, "Release WO {$wo->code}", $wo->code);

        return $this->show($id);
    }

    public function close(Request $request, int $id)
    {
        $wo = prd_wo_main::findOrFail($id);
        if ((int) $wo->status !== self::RELEASED) {
            throw BizException::make('WO_STATE', 'Hanya WO RELEASED yang dapat di-close.');
        }
        $wo->update(['status' => self::CLOSED]);
        AuditLogger::record($request, "Close WO {$wo->code}", $wo->code);

        return $this->show($id);
    }

    public function cancel(Request $request, int $id)
    {
        $wo = prd_wo_main::findOrFail($id);
        if (! in_array((int) $wo->status, [self::DRAFT, self::RELEASED], true)) {
            throw BizException::make('WO_STATE', 'WO ini tidak dapat dibatalkan.');
        }
        $wo->update(['status' => self::CANCELLED]);
        AuditLogger::record($request, "Cancel WO {$wo->code}", $wo->code);

        return $this->show($id);
    }

    /* -------- helpers -------- */

    private function spec(?m_item $it): string
    {
        if (! $it) {
            return '';
        }
        $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
        $p = [];
        if ((float) $it->o_d) $p[] = 'OD:' . $fmt($it->o_d);
        if ((float) $it->i_d) $p[] = 'ID:' . $fmt($it->i_d);
        if ((float) $it->thick) $p[] = 'T:' . $fmt($it->thick);
        if ((float) $it->length) $p[] = 'L:' . $fmt($it->length);

        return implode(' / ', $p);
    }

    /** WO must come from an APPROVED MPS; qty capped by the MPS remaining. */
    private function assertMps(array $data, ?int $excludeWo): void
    {
        $mps = prd_mps::find($data['mps_id']);
        if (! $mps) {
            throw BizException::make('WO_MPS', 'MPS tidak ditemukan.');
        }
        if ($mps->status !== 'APPROVED') {
            throw BizException::make('WO_MPS_STATE', 'WO hanya dapat dibuat dari MPS yang sudah APPROVED.');
        }
        if ((int) $data['fg_id'] !== (int) $mps->item_id) {
            throw BizException::make('WO_MPS_FG', 'Item FG tidak sesuai dengan MPS terpilih.');
        }
        $existing = (int) prd_wo_main::where('mps_id', $mps->id)->where('status', '<>', self::CANCELLED)
            ->when($excludeWo, fn ($q) => $q->where('id', '<>', $excludeWo))->sum('qty');
        if ($existing + (int) $data['qty'] > (int) $mps->qty) {
            $left = max(0, (int) $mps->qty - $existing);
            throw BizException::make('WO_OVER_MPS', "Qty WO melebihi sisa MPS (sisa {$left} dari {$mps->qty}).");
        }
    }

    /** @return array{0: array<int>, 1: array<int>} valid RM & PM item ids from the FG BOM */
    private function bomIds(int $fgId): array
    {
        $bom = m_bom::with(['rmLines', 'pmLines'])->where('item_id', $fgId)->first();
        if (! $bom || ($bom->rmLines->isEmpty() && $bom->pmLines->isEmpty())) {
            throw BizException::make('WO_NO_BOM', 'Item FG belum memiliki BOM (RM/PM). Lengkapi BOM di Item Master dulu.');
        }

        return [$bom->rmLines->pluck('mat_id')->all(), $bom->pmLines->pluck('pm_id')->all()];
    }

    private function assertLines(array $data, array $rmIds, array $pmIds, int $excludeWo): void
    {
        // booked serials on other open WOs
        $bookedElsewhere = prd_wo_serial_rm::query()
            ->whereHas('detail.main', fn ($q) => $q->whereIn('status', [self::DRAFT, self::RELEASED])->where('id', '<>', $excludeWo))
            ->pluck('serial_id')->map(fn ($v) => (string) $v)->all();

        foreach ($data['rm_lines'] ?? [] as $i => $l) {
            if (! in_array((int) $l['rm_id'], $rmIds, true)) {
                throw BizException::make('WO_RM', 'Material RM baris #' . ($i + 1) . ' tidak ada di BOM FG.');
            }
            foreach ($l['serials'] ?? [] as $s) {
                if (in_array((string) $s['serial_id'], $bookedElsewhere, true)) {
                    throw BizException::make('WO_BOOKED', "Serial '{$s['serial_id']}' sudah dibooking WO lain.");
                }
            }
        }
        foreach ($data['pm_lines'] ?? [] as $i => $l) {
            if (! in_array((int) $l['pm_id'], $pmIds, true)) {
                throw BizException::make('WO_PM', 'Material PM baris #' . ($i + 1) . ' tidak ada di BOM FG.');
            }
        }
    }

    private function syncLines(prd_wo_main $wo, array $data): void
    {
        foreach ($data['rm_lines'] ?? [] as $l) {
            $det = $wo->detailRm()->create(['rm_id' => $l['rm_id'], 'note' => $l['note'] ?? null]);
            foreach ($l['serials'] ?? [] as $s) {
                $lenSerial = (float) ($s['length_serial'] ?? 0);
                $book = (float) ($s['length_book'] ?? 0);
                $det->serials()->create([
                    'serial_id' => $s['serial_id'],
                    'length_asal' => $lenSerial,
                    'length_book' => $book,
                    'qty_per_serial' => (int) ($s['qty'] ?? 0),
                    'length_rem' => isset($s['length_rem']) ? (float) $s['length_rem'] : round($lenSerial - $book, 2),
                    'qty_serial' => 1,
                    'scrap' => (int) ($s['scrap'] ?? 0),
                    'note' => $s['note'] ?? null,
                ]);
            }
        }
        foreach ($data['pm_lines'] ?? [] as $l) {
            $det = $wo->detailPm()->create(['pm_id' => $l['pm_id'], 'code_tr' => '-', 'note' => $l['note'] ?? null]);
            foreach ($l['serials'] ?? [] as $s) {
                $det->serials()->create(['serial_id' => $s['serial_id'], 'qty' => (int) ($s['qty'] ?? 1), 'note' => $s['note'] ?? null]);
            }
        }
    }

    /** Enrich a saved WO with per-line requirement vs booked figures. */
    private function present(prd_wo_main $wo): array
    {
        $qty = (int) $wo->qty;
        $bom = m_bom::with(['rmLines', 'pmLines'])->where('item_id', $wo->fg_id)->first();
        $rmByMat = $bom ? $bom->rmLines->keyBy('mat_id') : collect();
        $pmByItem = $bom ? $bom->pmLines->keyBy('pm_id') : collect();

        $data = $wo->toArray();
        $data['status_label'] = $this->statusLabel((int) $wo->status);

        $data['detail_rm'] = $wo->detailRm->map(function ($d) use ($qty, $rmByMat) {
            $line = $rmByMat->get($d->rm_id);
            $lengthUse = $line ? (float) $line->length_use : 0;
            $reqLen = round($qty * $lengthUse, 2);
            $bookedPcs = (int) $d->serials->sum('qty_per_serial');
            return [
                'id' => $d->id, 'rm_id' => $d->rm_id, 'note' => $d->note,
                'rm' => $d->rm?->only(['id', 'code', 'part_name', 'o_d', 'i_d', 'thick']),
                'length_use' => $lengthUse, 'req_qty' => $qty, 'req_length' => $reqLen,
                'booked_pcs' => $bookedPcs, 'shortage' => max(0, $qty - $bookedPcs),
                'serials' => $d->serials->map(fn ($s) => [
                    'id' => $s->id, 'serial_id' => $s->serial_id,
                    'length_asal' => (float) $s->length_asal, 'length_book' => (float) $s->length_book,
                    'length_rem' => (float) $s->length_rem, 'qty' => (int) $s->qty_per_serial,
                    'scrap' => (int) $s->scrap, 'note' => $s->note,
                ])->all(),
            ];
        })->all();

        $data['detail_pm'] = $wo->detailPm->map(function ($d) use ($qty, $pmByItem) {
            $line = $pmByItem->get($d->pm_id);
            $per = $line ? (int) $line->qty : 0;
            return [
                'id' => $d->id, 'pm_id' => $d->pm_id, 'note' => $d->note,
                'pm' => $d->pm?->only(['id', 'code', 'part_name']),
                'per_fg' => $per, 'req_qty' => $qty * $per,
                'booked_qty' => (int) $d->serials->sum('qty'),
                'serials' => $d->serials->map(fn ($s) => ['id' => $s->id, 'serial_id' => $s->serial_id, 'qty' => (int) $s->qty, 'note' => $s->note])->all(),
            ];
        })->all();

        return $data;
    }

    private function statusLabel(int $s): string
    {
        return [self::DRAFT => '1 · Draft', self::RELEASED => '2 · Released', self::CLOSED => '3 · Closed', self::CANCELLED => 'Cancelled'][$s] ?? (string) $s;
    }

    private function assertDraft(prd_wo_main $wo): void
    {
        if ((int) $wo->status !== self::DRAFT) {
            throw BizException::make('WO_LOCKED', 'WO yang sudah di-release tidak dapat diubah.');
        }
    }

    private function validateWo(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'mps_id' => ['required', 'integer', 'exists:prd_mps,id'],
            'fg_id' => ['required', 'integer', 'exists:m_item,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'customer_id' => ['required', 'integer', 'exists:m_contacts,id'],
            'so_id' => ['nullable', 'string', 'max:50'],
            'no_cut' => ['nullable', 'boolean'],
            'for_pm' => ['nullable', 'boolean'],
            'rm_lines' => ['array'],
            'rm_lines.*.rm_id' => ['required', 'integer', 'exists:m_item,id'],
            'rm_lines.*.note' => ['nullable', 'string', 'max:150'],
            'rm_lines.*.serials' => ['array'],
            'rm_lines.*.serials.*.serial_id' => ['required', 'string', 'max:50'],
            'rm_lines.*.serials.*.length_serial' => ['nullable', 'numeric', 'min:0'],
            'rm_lines.*.serials.*.length_book' => ['nullable', 'numeric', 'min:0'],
            'rm_lines.*.serials.*.qty' => ['nullable', 'integer', 'min:0'],
            'rm_lines.*.serials.*.length_rem' => ['nullable', 'numeric', 'min:0'],
            'rm_lines.*.serials.*.scrap' => ['nullable', 'boolean'],
            'rm_lines.*.serials.*.note' => ['nullable', 'string', 'max:150'],
            'pm_lines' => ['array'],
            'pm_lines.*.pm_id' => ['required', 'integer', 'exists:m_item,id'],
            'pm_lines.*.note' => ['nullable', 'string', 'max:150'],
            'pm_lines.*.serials' => ['array'],
            'pm_lines.*.serials.*.serial_id' => ['required', 'string', 'max:50'],
            'pm_lines.*.serials.*.qty' => ['nullable', 'integer', 'min:0'],
            'pm_lines.*.serials.*.note' => ['nullable', 'string', 'max:150'],
        ]);
    }
}
