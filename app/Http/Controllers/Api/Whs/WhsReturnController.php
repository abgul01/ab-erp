<?php

namespace App\Http\Controllers\Api\Whs;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\whs_ret_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use App\Support\WhsPostingService;
use App\Support\WhsStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pengembalian alat pinjaman.
 *
 * Alat yang kembali baik masuk stok lagi. Yang rusak atau hilang keluar dari
 * stok dan baru pada saat itu dibebankan sebagai kerugian — bukan saat
 * dipinjamkan, karena selama masih di tangan orang, alat itu masih ada.
 */
class WhsReturnController extends Controller
{
    private array $with = ['detail.unit.item'];

    public function __construct(
        private WhsPostingService $posting,
        private WhsStockService $stock,
    ) {}

    public function index(Request $request)
    {
        $rows = whs_ret_main::withCount('detail')
            ->when($request->query('q'), fn ($q, $s) => $q->where('code', 'like', "%{$s}%")
                ->orWhere('returner', 'like', "%{$s}%"))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        return ApiResponse::item(whs_ret_main::with($this->with)->findOrFail($id));
    }

    /** Alat yang sedang di luar gudang: unit mana, di siapa, sudah berapa lama. */
    public function onLoan()
    {
        return ApiResponse::collection($this->stock->onLoan());
    }

    public function store(Request $request)
    {
        $data = $this->validateRet($request);

        $ret = DB::transaction(function () use ($data, $request) {
            $ret = whs_ret_main::create([
                'code' => app(NumberingService::class)->next('WHSRET', 'WRT'),
                'date' => $data['date'],
                'returner' => $data['returner'] ?? null,
                'note' => $data['note'] ?? null,
                'user_id' => $request->user()->id,
                'status' => 'DRAFT',
            ]);
            $this->syncLines($ret, $data['lines']);
            AuditLogger::record($request, "Create Pengembalian Alat {$ret->code}", $ret->code);

            return $ret;
        });

        return ApiResponse::item($ret->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $ret = whs_ret_main::findOrFail($id);
        $this->assertDraft($ret);
        $data = $this->validateRet($request);

        DB::transaction(function () use ($ret, $data, $request) {
            $ret->update([
                'date' => $data['date'],
                'returner' => $data['returner'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
            $ret->detail()->delete();
            $this->syncLines($ret, $data['lines']);
            AuditLogger::record($request, "Update Pengembalian Alat {$ret->code}", $ret->code);
        });

        return ApiResponse::item($ret->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $ret = whs_ret_main::findOrFail($id);
        $this->assertDraft($ret);

        DB::transaction(function () use ($ret, $request) {
            $ret->detail()->delete();
            $ret->delete();
            AuditLogger::record($request, "Delete Pengembalian Alat {$ret->code}", $ret->code);
        });

        return ApiResponse::item(['message' => 'Pengembalian alat dihapus.']);
    }

    public function post(Request $request, int $id)
    {
        $ret = whs_ret_main::findOrFail($id);
        $posted = $this->posting->postReturn($ret, $request->user()->id);

        AuditLogger::record($request, "Post Pengembalian Alat {$ret->code}", $ret->code);

        return ApiResponse::item($posted->load($this->with));
    }

    /* ---------------- helpers ---------------- */

    private function assertDraft(whs_ret_main $ret): void
    {
        if ($ret->status !== 'DRAFT') {
            throw BizException::make('WHS_RET_LOCKED', 'Pengembalian yang sudah di-post tidak dapat diubah.');
        }
    }

    private function syncLines(whs_ret_main $ret, array $lines): void
    {
        foreach ($lines as $l) {
            // Baris pengeluaran asalnya diambil dari unitnya sendiri kalau tidak
            // dikirim: itu satu-satunya sumber yang pasti benar.
            $outDetId = $l['out_det_id']
                ?? DB::table('whs_tool_unit')->where('id', $l['tool_unit_id'])->value('out_det_id');

            $ret->detail()->create([
                'out_det_id' => $outDetId,
                'tool_unit_id' => $l['tool_unit_id'],
                'condition' => $l['condition'] ?? 'GOOD',
                'note' => $l['note'] ?? null,
            ]);
        }
    }

    private function validateRet(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'returner' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:300'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.tool_unit_id' => ['required', 'integer', 'exists:whs_tool_unit,id'],
            'lines.*.out_det_id' => ['nullable', 'integer', 'exists:whs_out_det,id'],
            'lines.*.condition' => ['nullable', 'in:GOOD,DAMAGED,LOST'],
            'lines.*.note' => ['nullable', 'string', 'max:150'],
        ]);
    }
}
