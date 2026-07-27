<?php

namespace App\Http\Controllers\Api\Wms;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\wh_out_main;
use App\Models\wh_rem_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Outgoing RM (wh_out). Serial keluar gudang untuk produksi — nantinya wajib
 * per Work Order (wo_id disiapkan, opsional sampai modul WO ada). Panjang
 * terpakai dicatat per serial (length_used); sisa (length_rem) dapat langsung
 * dikembalikan ke rak remnant (membuat dokumen Remaining/Tankan inline) atau
 * lewat transaksi Remaining tersendiri.
 */
class OutgoingController extends Controller
{
    private array $with = ['item', 'user', 'wo', 'detail'];

    public function index(Request $request)
    {
        $query = wh_out_main::with(['item', 'user'])->withCount('detail');
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where('code', 'like', "%{$q}%");
        }
        $query->orderByDesc('id');

        return ApiResponse::paginated($query->paginate(min(max((int) $request->query('per_page', 20), 1), 200)));
    }

    public function show(int $id)
    {
        $doc = wh_out_main::with($this->with)->findOrFail($id);
        $rem = wh_rem_main::with('detail.rack')->where('out_id', $id)->get();
        $data = $doc->toArray();
        $data['remaining_docs'] = $rem;

        return ApiResponse::item($data);
    }

    /**
     * Scan a WO / Denpyou at the counter. Accepts the WO code itself, the
     * "OP-{woId}" denpyou form, or a WIP code, and answers with the RM the WO
     * needs plus every serial booked to it — that is what the storeman may
     * hand out, and what the "View Serial" button lists.
     */
    public function checkWo(string $code)
    {
        $code = trim($code);
        $wo = \App\Models\prd_wo_main::with(['fg', 'detailRm.rm', 'detailRm.serials'])->where('code', $code)->first();
        if (! $wo) {
            $wipId = DB::table('prd_wip')->where('no_dp', $code)->orWhere('code', $code)->value('wo_id');
            if ($wipId) {
                $wo = \App\Models\prd_wo_main::with(['fg', 'detailRm.rm', 'detailRm.serials'])->find($wipId);
            }
        }
        if (! $wo && preg_match('/(\d+)\s*$/', $code, $m)) {
            $wo = \App\Models\prd_wo_main::with(['fg', 'detailRm.rm', 'detailRm.serials'])->find((int) ltrim($m[1], '0'));
        }
        if (! $wo) {
            throw BizException::make('OUT_WO', "WO / Denpyou '{$code}' tidak ditemukan.");
        }
        if ((int) $wo->status !== \App\Http\Controllers\Api\Production\WoController::RELEASED) {
            throw BizException::make('OUT_WO_STATE', "WO {$wo->code} belum/tidak lagi berstatus Released.");
        }

        // serials already issued must not be offered again
        $issued = DB::table('wh_out_detail')->pluck('serial_id')->map(fn ($v) => (string) $v)->flip();

        $items = $wo->detailRm->map(fn ($d) => [
            'item_id' => (int) $d->rm_id,
            'code' => $d->rm?->code, 'part_name' => $d->rm?->part_name,
            'serials' => $d->serials->map(fn ($s) => [
                'serial_id' => (string) $s->serial_id,
                'length_book' => (float) $s->length_book,
                'length_rem' => (float) $s->length_rem,
                'qty' => (int) $s->qty_per_serial,
                'issued' => $issued->has((string) $s->serial_id),
            ])->values(),
        ])->values();

        return ApiResponse::item([
            'wo_id' => $wo->id, 'wo_code' => $wo->code,
            'no_dp' => DB::table('prd_wip')->where('wo_id', $wo->id)->value('no_dp'),
            'fg' => $wo->fg?->only(['id', 'code', 'part_name']),
            'qty' => (int) $wo->qty,
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'shift_id' => ['nullable', 'integer'],
            'wo_id' => ['nullable', 'integer'],
            'cus_id' => ['nullable', 'integer', 'exists:m_contacts,id'],
            'item_id' => ['required', 'integer', 'exists:m_item,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.serial_id' => ['required', 'string', 'max:50'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.length_used' => ['nullable', 'numeric', 'min:0'],
            'lines.*.rem' => ['nullable', 'boolean'],
            'lines.*.rem_rack_id' => ['nullable', 'integer', 'exists:m_rack,id'],
        ]);

        $states = $this->currentStates(array_column($data['lines'], 'serial_id'));

        // Issue per Work Order: item must be an RM of the WO and serials must be
        // booked to it (WO-driven issue). Without a WO, free issue of on-hand.
        $bookedSerials = null;
        if (! empty($data['wo_id'])) {
            $wo = \App\Models\prd_wo_main::with('detailRm.serials')->find($data['wo_id']);
            if (! $wo) {
                throw BizException::make('OUT_WO', 'Work Order tidak ditemukan.');
            }
            if ((int) $wo->status !== \App\Http\Controllers\Api\Production\WoController::RELEASED) {
                throw BizException::make('OUT_WO_STATE', 'Issue RM hanya untuk WO berstatus Released.');
            }
            $rmDetail = $wo->detailRm->firstWhere('rm_id', (int) $data['item_id']);
            if (! $rmDetail) {
                throw BizException::make('OUT_WO_ITEM', 'Item ini bukan kebutuhan RM dari WO terpilih.');
            }
            $bookedSerials = $rmDetail->serials->pluck('serial_id')->map(fn ($v) => (string) $v)->all();
        }

        foreach ($data['lines'] as $i => $l) {
            $st = $states[$l['serial_id']] ?? null;
            if (! $st) {
                throw BizException::make('OUT_STOCK', 'Baris #' . ($i + 1) . ": serial '{$l['serial_id']}' tidak ada di stok on-hand.");
            }
            if ((int) $st['item_id'] !== (int) $data['item_id']) {
                throw BizException::make('OUT_ITEM', 'Baris #' . ($i + 1) . ": serial '{$l['serial_id']}' bukan item dokumen ini.");
            }
            if ($bookedSerials !== null && ! in_array((string) $l['serial_id'], $bookedSerials, true)) {
                throw BizException::make('OUT_NOT_BOOKED', 'Baris #' . ($i + 1) . ": serial '{$l['serial_id']}' belum dibooking ke WO ini.");
            }
            $used = (float) ($l['length_used'] ?? $st['length']);
            if ($used > (float) $st['length']) {
                throw BizException::make('OUT_LEN', 'Baris #' . ($i + 1) . ": length_used ({$used}) melebihi panjang serial ({$st['length']}).");
            }
            if (! empty($l['rem']) && empty($l['rem_rack_id'])) {
                throw BizException::make('OUT_REMRACK', 'Baris #' . ($i + 1) . ': pilih rak remnant untuk pengembalian sisa.');
            }
        }

        $shiftId = $data['shift_id'] ?? DB::table('m_shift')->orderBy('id')->value('id');

        $doc = DB::transaction(function () use ($data, $states, $request, $shiftId) {
            $doc = wh_out_main::create([
                'code' => (new NumberingService)->next('WH_OUT', 'OUT'),
                'wo_id' => $data['wo_id'] ?? null,
                'item_id' => $data['item_id'],
                'user_id' => $request->user()->id,
                'date' => $data['date'],
                'cus_id' => $data['cus_id'] ?? null,
                'shift_id' => $shiftId,
            ]);

            $remLines = [];
            foreach ($data['lines'] as $l) {
                $st = $states[$l['serial_id']];
                $lengthSerial = (float) $st['length'];
                $used = (float) ($l['length_used'] ?? $lengthSerial);
                $remLen = round($lengthSerial - $used, 2);
                $weight = (float) ($st['weight'] ?? 0);
                $weightUsed = $lengthSerial > 0 ? round($weight * ($used / $lengthSerial), 2) : $weight;
                $withRem = ! empty($l['rem']) && $remLen > 0;

                $doc->detail()->create([
                    'serial_id' => $l['serial_id'],
                    'qty' => $l['qty'],
                    'pm' => 0,
                    'length_serial' => $lengthSerial,
                    'length_used' => $used,
                    'length_rem' => $remLen,
                    'weight_used' => $weightUsed,
                    'rem_data' => $withRem ? 1 : 0,
                ]);

                if ($withRem) {
                    $remLines[] = [
                        'serial_id' => $l['serial_id'],
                        'length' => $remLen,
                        'weight' => round($weight - $weightUsed, 2),
                        'rack_id' => $l['rem_rack_id'],
                    ];
                }
            }

            if ($remLines) {
                $rem = wh_rem_main::create([
                    'out_id' => $doc->id,
                    'code' => (new NumberingService)->next('WH_REM', 'REM'),
                    'date' => $data['date'],
                    'user_id' => $request->user()->id,
                    'item_id' => $data['item_id'],
                    'shift_id' => $shiftId,
                ]);
                foreach ($remLines as $r) {
                    $rem->detail()->create([
                        'serial_id' => $r['serial_id'],
                        'wo_id' => $data['wo_id'] ?? 0,
                        'length' => $r['length'],
                        'weight' => $r['weight'],
                        'rem_count' => 1,
                        'rack_id' => $r['rack_id'],
                    ]);
                }
                AuditLogger::record($request, "Remaining {$rem->code} (inline dari {$doc->code})", $rem->code);
            }

            AuditLogger::record($request, "Outgoing {$doc->code}", $doc->code);

            return $doc;
        });

        return $this->show($doc->id)->setStatusCode(201);
    }

    public function destroy(Request $request, int $id)
    {
        $doc = wh_out_main::with('detail')->findOrFail($id);
        if (wh_rem_main::where('out_id', $id)->exists()) {
            throw BizException::make('OUT_HAS_REM', 'Ada dokumen Remaining terkait — hapus Remaining tersebut dulu.');
        }

        DB::transaction(function () use ($doc, $request) {
            $doc->detail()->delete();
            $doc->delete();
            AuditLogger::record($request, "Delete Outgoing {$doc->code}", $doc->code);
        });

        return ApiResponse::item(['message' => 'Outgoing dibatalkan, serial kembali on-hand.']);
    }

    /**
     * Current on-hand state per serial: FULL (incoming, belum keluar) memakai
     * panjang/berat asal; REMNANT (hasil tankan terakhir yang belum keluar lagi)
     * memakai panjang/berat sisa.
     *
     * @return array<string, array{item_id:int, length:float, weight:float, source:string}>
     */
    private function currentStates(array $serialIds): array
    {
        $states = [];
        $serialIds = array_values(array_unique($serialIds));

        $incs = DB::table('wh_inc_detail as wd')
            ->leftJoin('prc_gr_serial as gs', 'gs.serial_id', '=', 'wd.serial_id')
            ->whereIn('wd.serial_id', $serialIds)
            ->select('wd.serial_id', 'wd.item_id', 'wd.length', 'gs.weight', 'wd.created_at')
            ->get()->keyBy('serial_id');

        foreach ($serialIds as $sid) {
            $inc = $incs[$sid] ?? null;
            if (! $inc) {
                continue;
            }
            // Outgoing termuda (id tertinggi) yang memuat serial ini.
            $lastOutId = DB::table('wh_out_detail as od')->join('wh_out_main as om', 'om.id', '=', 'od.id_prim')
                ->where('od.serial_id', $sid)->max('om.id');

            if (! $lastOutId) {
                $states[$sid] = ['item_id' => $inc->item_id, 'length' => (float) $inc->length, 'weight' => (float) ($inc->weight ?? 0), 'source' => 'FULL'];
                continue;
            }
            // Remnant yang dihasilkan outgoing terakhir itu = sisa yang masih on-hand.
            $rem = DB::table('wh_rem_detail as rd')->join('wh_rem_main as rm', 'rm.id', '=', 'rd.id_prim')
                ->where('rd.serial_id', $sid)->where('rm.out_id', $lastOutId)
                ->orderByDesc('rd.id')->select('rd.length', 'rd.weight', 'rm.item_id')->first();
            if ($rem) {
                $states[$sid] = ['item_id' => $rem->item_id, 'length' => (float) $rem->length, 'weight' => (float) ($rem->weight ?? 0), 'source' => 'REMNANT'];
            }
        }

        return $states;
    }
}
