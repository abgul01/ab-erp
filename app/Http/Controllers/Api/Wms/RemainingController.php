<?php

namespace App\Http\Controllers\Api\Wms;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\wh_out_main;
use App\Models\wh_rem_detail;
use App\Models\wh_rem_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use App\Support\UomConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Remaining / Tankan RM (wh_rem). Pengembalian sisa potongan dari sebuah
 * transaksi Outgoing ke rak remnant — dapat dibuat inline saat outgoing, atau
 * lewat transaksi ini (memilih dokumen outgoing sumber).
 */
class RemainingController extends Controller
{
    private array $with = ['out', 'item', 'user', 'detail.rack'];

    public function index(Request $request)
    {
        $query = wh_rem_main::with(['out', 'item', 'user'])->withCount('detail');
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where('code', 'like', "%{$q}%");
        }
        $query->orderByDesc('id');

        return ApiResponse::paginated($query->paginate(min(max((int) $request->query('per_page', 20), 1), 200)));
    }

    public function show(int $id)
    {
        return ApiResponse::item(wh_rem_main::with($this->with)->findOrFail($id));
    }

    /** Baris outgoing dengan sisa (length_rem>0) yang belum dikembalikan. */
    public function available(int $outId)
    {
        $out = wh_out_main::with('detail')->findOrFail($outId);
        $returned = wh_rem_detail::whereIn('id_prim', wh_rem_main::where('out_id', $outId)->pluck('id'))
            ->pluck('serial_id')->all();

        $uom = app(UomConversionService::class);
        $rows = $out->detail
            ->filter(fn ($d) => (float) $d->length_rem > 0 && ! in_array($d->serial_id, $returned, true))
            ->map(fn ($d) => [
                'serial_id' => $d->serial_id,
                'length_rem' => (float) $d->length_rem,
                'weight_rem' => max(0, $uom->remKg((float) $d->length_rem, (float) $d->weight_used, (float) $d->length_serial)),
            ])->values();

        return ApiResponse::collection($rows);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'out_id' => ['required', 'integer', 'exists:wh_out_main,id'],
            'date' => ['required', 'date'],
            'shift_id' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.serial_id' => ['required', 'string', 'max:50'],
            'lines.*.length' => ['required', 'numeric', 'min:0.01'],
            'lines.*.weight' => ['nullable', 'numeric', 'min:0'],
            'lines.*.rack_id' => ['required', 'integer', 'exists:m_rack,id'],
        ]);

        $out = wh_out_main::with('detail')->findOrFail($data['out_id']);
        $outDetails = $out->detail->keyBy('serial_id');
        $returned = wh_rem_detail::whereIn('id_prim', wh_rem_main::where('out_id', $out->id)->pluck('id'))
            ->pluck('serial_id')->all();

        foreach ($data['lines'] as $i => $l) {
            $d = $outDetails->get($l['serial_id']);
            if (! $d) {
                throw BizException::make('RM_SERIAL', 'Baris #'.($i + 1).": serial '{$l['serial_id']}' tidak ada pada outgoing terpilih.");
            }
            if ((float) $d->length_rem <= 0) {
                throw BizException::make('RM_NOREM', 'Baris #'.($i + 1).": serial '{$l['serial_id']}' tidak memiliki sisa.");
            }
            if (in_array($l['serial_id'], $returned, true)) {
                throw BizException::make('RM_DUP', 'Baris #'.($i + 1).": sisa serial '{$l['serial_id']}' sudah pernah dikembalikan.");
            }
            if ((float) $l['length'] > (float) $d->length_rem) {
                throw BizException::make('RM_LEN', 'Baris #'.($i + 1).": panjang ({$l['length']}) melebihi sisa tercatat ({$d->length_rem}).");
            }
        }

        $shiftId = $data['shift_id'] ?? DB::table('m_shift')->orderBy('id')->value('id');

        $rem = DB::transaction(function () use ($data, $out, $outDetails, $request, $shiftId) {
            $rem = wh_rem_main::create([
                'out_id' => $out->id,
                'code' => (new NumberingService)->next('WH_REM', 'REM'),
                'date' => $data['date'],
                'user_id' => $request->user()->id,
                'item_id' => $out->item_id,
                'shift_id' => $shiftId,
            ]);
            foreach ($data['lines'] as $l) {
                $rem->detail()->create([
                    'serial_id' => $l['serial_id'],
                    'wo_id' => $out->wo_id ?? 0,
                    'length' => $l['length'],
                    'weight' => $l['weight'] ?? 0,
                    'rem_count' => 1,
                    'rack_id' => $l['rack_id'],
                ]);
                $outDetails[$l['serial_id']]->update(['rem_data' => 1]);
            }
            AuditLogger::record($request, "Remaining {$rem->code} (dari {$out->code})", $rem->code);

            return $rem;
        });

        return ApiResponse::item($rem->load($this->with), 201);
    }

    public function destroy(Request $request, int $id)
    {
        $rem = wh_rem_main::with('detail')->findOrFail($id);

        // Remnant yang sudah keluar lagi tidak boleh dibatalkan.
        foreach ($rem->detail as $d) {
            $reissued = DB::table('wh_out_detail')->where('serial_id', $d->serial_id)
                ->where('created_at', '>', $d->created_at)->exists();
            if ($reissued) {
                throw BizException::make('RM_REISSUED', "Serial '{$d->serial_id}' sudah keluar lagi dari rak remnant.");
            }
        }

        DB::transaction(function () use ($rem, $request) {
            $out = wh_out_main::with('detail')->find($rem->out_id);
            if ($out) {
                foreach ($rem->detail as $d) {
                    optional($out->detail->firstWhere('serial_id', $d->serial_id))->update(['rem_data' => 0]);
                }
            }
            $rem->detail()->delete();
            $rem->delete();
            AuditLogger::record($request, "Delete Remaining {$rem->code}", $rem->code);
        });

        return ApiResponse::item(['message' => 'Remaining dibatalkan.']);
    }
}
