<?php

namespace App\Http\Controllers\Api\Whs;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\whs_out_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use App\Support\WhsPostingService;
use App\Support\WhsStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pengeluaran barang WHS ke pemakai, mesin, atau aset.
 *
 * Sparepart dan barang habis pakai keluar dan jadi beban; alat keluar sebagai
 * pinjaman dan tetap tercatat sebagai milik perusahaan sampai dikembalikan.
 * Pembedaan itu terjadi di WhsPostingService, bukan di sini.
 */
class WhsOutgoingController extends Controller
{
    private array $with = ['detail.item'];

    public function __construct(
        private WhsPostingService $posting,
        private WhsStockService $stock,
    ) {}

    public function index(Request $request)
    {
        $rows = whs_out_main::withCount('detail')
            ->when($request->query('q'), fn ($q, $s) => $q->where('code', 'like', "%{$s}%")
                ->orWhere('receiver', 'like', "%{$s}%"))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        $out = whs_out_main::with($this->with)->findOrFail($id);

        // Unit alat yang keluar lewat dokumen ini, supaya terlihat unit mana
        // yang sedang dipegang siapa tanpa membuka layar lain.
        $out->setAttribute('units', DB::table('whs_tool_unit as u')
            ->join('whs_out_det as d', 'd.id', '=', 'u.out_det_id')
            ->where('d.main_id', $id)
            ->get(['u.id', 'u.code', 'u.status', 'u.holder', 'u.item_id', 'd.id as out_det_id']));

        return ApiResponse::item($out);
    }

    /** Barang yang masih ada stoknya — pemilih di layar pengeluaran. */
    public function availableItems(Request $request)
    {
        $rows = collect($this->stock->stockList($request->query('whs_type')))
            ->filter(fn ($r) => $r['qty'] > 0)
            ->values();

        return ApiResponse::collection($rows);
    }

    /**
     * Batch yang masih ada isinya untuk satu barang.
     *
     * Petugas memilih batch mana yang diambil dari rak; kalau dibiarkan kosong,
     * server mengambil yang paling lama.
     */
    public function serials(Request $request)
    {
        $request->validate(['item_id' => ['required', 'integer', 'exists:m_whs_item,id']]);

        return ApiResponse::collection(
            $this->stock->serialBalances((int) $request->query('item_id'), true)
        );
    }

    public function store(Request $request)
    {
        $data = $this->validateOut($request);

        $out = DB::transaction(function () use ($data, $request) {
            $out = whs_out_main::create([
                'code' => app(NumberingService::class)->next('WHSOUT', 'WOU'),
                'date' => $data['date'],
                'dept' => $data['dept'] ?? null,
                'receiver' => $data['receiver'] ?? null,
                'cost_center' => $data['cost_center'] ?? null,
                'note' => $data['note'] ?? null,
                'user_id' => $request->user()->id,
                'status' => 'DRAFT',
            ]);
            $this->syncLines($out, $data);
            AuditLogger::record($request, "Create Pengeluaran WHS {$out->code}", $out->code);

            return $out;
        });

        return ApiResponse::item($out->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $out = whs_out_main::findOrFail($id);
        $this->assertDraft($out);
        $data = $this->validateOut($request);

        DB::transaction(function () use ($out, $data, $request) {
            $out->update([
                'date' => $data['date'],
                'dept' => $data['dept'] ?? null,
                'receiver' => $data['receiver'] ?? null,
                'cost_center' => $data['cost_center'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
            $out->detail()->delete();
            $this->syncLines($out, $data);
            AuditLogger::record($request, "Update Pengeluaran WHS {$out->code}", $out->code);
        });

        return ApiResponse::item($out->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $out = whs_out_main::findOrFail($id);
        $this->assertDraft($out);

        DB::transaction(function () use ($out, $request) {
            $out->detail()->delete();
            $out->delete();
            AuditLogger::record($request, "Delete Pengeluaran WHS {$out->code}", $out->code);
        });

        return ApiResponse::item(['message' => 'Pengeluaran WHS dihapus.']);
    }

    /** Sahkan: stok berkurang, alat berpindah tangan, beban dibukukan. */
    public function post(Request $request, int $id)
    {
        $out = whs_out_main::findOrFail($id);
        $posted = $this->posting->postOutgoing($out, $request->user()->id);

        AuditLogger::record($request, "Post Pengeluaran WHS {$out->code}", $out->code);

        return ApiResponse::item($posted->load($this->with));
    }

    /* ---------------- helpers ---------------- */

    private function assertDraft(whs_out_main $out): void
    {
        if ($out->status !== 'DRAFT') {
            throw BizException::make('WHS_OUT_LOCKED', 'Pengeluaran yang sudah di-post tidak dapat diubah.');
        }
    }

    private function syncLines(whs_out_main $out, array $data): void
    {
        foreach ($data['lines'] as $l) {
            $out->detail()->create([
                'item_id' => $l['item_id'],
                // Boleh kosong: server memilih batch tertua saat di-post.
                'serial_code' => $l['serial_code'] ?? null,
                'qty' => $l['qty'],
                'unit_cost' => $l['unit_cost'] ?? 0,
                // Cost center per baris boleh berbeda dari header: satu
                // pengeluaran sering melayani dua mesin sekaligus.
                'cost_center' => $l['cost_center'] ?? $data['cost_center'] ?? null,
                'machine_id' => $l['machine_id'] ?? null,
                'asset_id' => $l['asset_id'] ?? null,
                'note' => $l['note'] ?? null,
            ]);
        }
    }

    private function validateOut(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'dept' => ['nullable', 'string', 'max:50'],
            'receiver' => ['nullable', 'string', 'max:60'],
            'cost_center' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:300'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:m_whs_item,id'],
            'lines.*.serial_code' => ['nullable', 'string', 'max:40'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.cost_center' => ['nullable', 'string', 'max:40'],
            'lines.*.machine_id' => ['nullable', 'integer', 'exists:m_machine,id'],
            'lines.*.asset_id' => ['nullable', 'integer', 'exists:ast_main,id'],
            'lines.*.note' => ['nullable', 'string', 'max:150'],
        ]);
    }
}
