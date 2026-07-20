<?php

namespace App\Http\Controllers\Api\Wms;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\prc_gr_main;
use App\Models\wh_inc_detail;
use App\Models\wh_inc_main;
use App\Models\wh_out_detail;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Incoming RM (wh_inc). Serial GR (status OK) masuk gudang ke rak. Form dapat
 * memilih BANYAK GR sekaligus (multiple choice / select all); penyimpanan
 * dikelompokkan menjadi satu dokumen wh_inc per GR (kolom gr_id tunggal).
 */
class IncomingController extends Controller
{
    private array $with = ['gr.ven', 'user', 'detail.item', 'detail.rack'];

    public function index(Request $request)
    {
        $query = wh_inc_main::with(['gr', 'user'])->withCount('detail');
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where('code', 'like', "%{$q}%")
                ->orWhereHas('gr', fn ($s) => $s->where('code', 'like', "%{$q}%"));
        }
        $query->orderByDesc('id');

        return ApiResponse::paginated($query->paginate(min(max((int) $request->query('per_page', 20), 1), 200)));
    }

    public function show(int $id)
    {
        return ApiResponse::item(wh_inc_main::with($this->with)->findOrFail($id));
    }

    /** Serial OK yang belum masuk gudang, untuk banyak GR sekaligus (?gr_ids=1,2,3). */
    public function available(Request $request)
    {
        $grIds = array_filter(array_map('intval', explode(',', (string) $request->query('gr_ids', ''))));
        if (! $grIds) {
            return ApiResponse::collection([]);
        }
        $grs = prc_gr_main::with('detail.item', 'detail.serials')->whereIn('id', $grIds)->get();
        $allSerialIds = $grs->flatMap(fn ($g) => $g->detail->flatMap->serials->pluck('serial_id'));
        $placed = wh_inc_detail::whereIn('serial_id', $allSerialIds)->pluck('serial_id')->all();

        $rows = [];
        foreach ($grs as $gr) {
            foreach ($gr->detail as $det) {
                foreach ($det->serials as $s) {
                    if (($s->status ?? 'OK') === 'NG' || in_array($s->serial_id, $placed, true)) {
                        continue;
                    }
                    $rows[] = [
                        'gr_id' => $gr->id,
                        'gr_code' => $gr->code,
                        'serial_id' => $s->serial_id,
                        'item_id' => $det->item_id,
                        'item_label' => optional($det->item)->code . ' — ' . optional($det->item)->part_name,
                        'qty' => $s->qty,
                        'length' => $s->length,
                        'weight' => $s->weight,
                        'millsheet' => $s->millsheet,
                    ];
                }
            }
        }

        return ApiResponse::collection($rows);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'shift_id' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.gr_id' => ['required', 'integer', 'exists:prc_gr_main,id'],
            'lines.*.serial_id' => ['required', 'string', 'max:50'],
            'lines.*.item_id' => ['required', 'integer', 'exists:m_item,id'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.length' => ['nullable', 'integer', 'min:0'],
            'lines.*.rack_id' => ['required', 'integer', 'exists:m_rack,id'],
        ]);

        $byGr = collect($data['lines'])->groupBy('gr_id');
        $grs = prc_gr_main::with('detail.serials')->whereIn('id', $byGr->keys())->get()->keyBy('id');

        foreach ($data['lines'] as $i => $l) {
            $gr = $grs[$l['gr_id']] ?? null;
            $s = $gr ? $gr->detail->flatMap->serials->firstWhere('serial_id', $l['serial_id']) : null;
            if (! $s) {
                throw BizException::make('IN_SERIAL', 'Baris #' . ($i + 1) . ": serial '{$l['serial_id']}' tidak ada pada GR terpilih.");
            }
            if (($s->status ?? 'OK') === 'NG') {
                throw BizException::make('IN_NG', 'Baris #' . ($i + 1) . ": serial '{$l['serial_id']}' berstatus NG.");
            }
            if (wh_inc_detail::where('serial_id', $l['serial_id'])->exists()) {
                throw BizException::make('IN_DUP', 'Baris #' . ($i + 1) . ": serial '{$l['serial_id']}' sudah pernah incoming.");
            }
        }

        $shiftId = $data['shift_id'] ?? DB::table('m_shift')->orderBy('id')->value('id');
        if (! $shiftId) {
            throw BizException::make('IN_SHIFT', 'Master shift kosong — isi m_shift terlebih dulu.');
        }

        $docs = DB::transaction(function () use ($byGr, $data, $request, $shiftId) {
            $docs = [];
            foreach ($byGr as $grId => $lines) {
                $doc = wh_inc_main::create([
                    'code' => (new NumberingService)->next('WH_IN', 'WI'),
                    'user_id' => $request->user()->id,
                    'gr_id' => $grId,
                    'date' => $data['date'],
                    'shift_id' => $shiftId,
                ]);
                foreach ($lines as $l) {
                    $doc->detail()->create([
                        'serial_id' => $l['serial_id'],
                        'item_id' => $l['item_id'],
                        'qty' => $l['qty'],
                        'length' => $l['length'] ?? 0,
                        'rack_id' => $l['rack_id'],
                    ]);
                }
                AuditLogger::record($request, "Incoming {$doc->code} (GR#{$grId})", $doc->code);
                $docs[] = $doc->load($this->with);
            }

            return $docs;
        });

        return ApiResponse::collection($docs);
    }

    public function destroy(Request $request, int $id)
    {
        $doc = wh_inc_main::with('detail')->findOrFail($id);

        if (wh_out_detail::whereIn('serial_id', $doc->detail->pluck('serial_id'))->exists()) {
            throw BizException::make('IN_ISSUED', 'Sebagian serial sudah keluar (outgoing), incoming tidak dapat dibatalkan.');
        }

        DB::transaction(function () use ($doc, $request) {
            $doc->detail()->delete();
            $doc->delete();
            AuditLogger::record($request, "Delete Incoming {$doc->code}", $doc->code);
        });

        return ApiResponse::item(['message' => 'Incoming dibatalkan, serial kembali tersedia.']);
    }
}
