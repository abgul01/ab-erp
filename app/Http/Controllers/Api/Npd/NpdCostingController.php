<?php

namespace App\Http\Controllers\Api\Npd;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\npd_bom_main;
use App\Models\npd_cost_main;
use App\Models\npd_project;
use App\Support\ApiResponse;
use App\Support\ApprovalEngine;
use App\Support\AuditLogger;
use App\Support\NpdCostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Preliminary BOM, estimasi biaya, dan quotation proyek NPD.
 *
 * PRD_Modul_NPD_FTPI.md §7.4
 */
class NpdCostingController extends Controller
{
    public function __construct(private NpdCostingService $svc) {}

    /** Semua BOM dan estimasi milik satu proyek. */
    public function show(int $projectId)
    {
        $project = npd_project::with('item')->findOrFail($projectId);

        return ApiResponse::item([
            'project' => $project,
            'boms' => npd_bom_main::with(['detail.item', 'detail.ven'])
                ->where('main_id', $projectId)->orderByDesc('id')->get()
                ->map(fn ($b) => $b->setAttribute('detail', $b->detail->map(fn ($d) => $d->toArray() + ['label' => $d->label()]))),
            'costs' => npd_cost_main::with(['detail.proc', 'detail.item'])
                ->where('main_id', $projectId)->orderByDesc('id')->get(),
        ]);
    }

    /* ---------------- pendaftaran part ---------------- */

    /**
     * Daftarkan part proyek ke master item (non-aktif).
     *
     * Prasyarat BOM yang menunjuk part itu sendiri, Work Order trial, dan
     * pricelist — ketiganya butuh `m_item` yang nyata.
     */
    public function registerPart(Request $request, int $projectId)
    {
        $project = npd_project::findOrFail($projectId);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'part_name' => ['nullable', 'string', 'max:50'],
            // Golongan barang menentukan perlakuan seluruh sistem terhadapnya.
            'type' => ['required', 'in:RM,PM,FG'],
            'category_id' => ['required', 'integer', 'exists:m_i_category,id'],
            'o_d' => ['nullable', 'numeric'],
            'thick' => ['nullable', 'numeric'],
            'length' => ['nullable', 'numeric'],
            'weight' => ['nullable', 'numeric'],
            'tolerance' => ['nullable', 'string', 'max:50'],
        ]);

        $item = $this->svc->registerPart($project, $data, $request->user()->id);

        return ApiResponse::item($project->fresh()->load('item'), 201)
            ->withHeaders(['X-Item-Id' => $item->id]);
    }

    /* ---------------- BOM ---------------- */

    public function storeBom(Request $request, int $projectId)
    {
        npd_project::findOrFail($projectId);
        $data = $this->validateBom($request);

        $bom = DB::transaction(function () use ($projectId, $data, $request) {
            $bom = npd_bom_main::create([
                'main_id' => $projectId,
                'version' => $data['version'],
                'effective_date' => $data['effective_date'] ?? null,
                'note' => $data['note'] ?? null,
                'user_id' => $request->user()->id,
                'status' => 'DRAFT',
            ]);
            $this->syncBomLines($bom, $data['lines'] ?? []);

            AuditLogger::record($request, "Buat preliminary BOM {$bom->version} proyek #{$projectId}");

            return $bom;
        });

        return ApiResponse::item($bom->load('detail.item'), 201);
    }

    public function updateBom(Request $request, int $id)
    {
        $bom = npd_bom_main::findOrFail($id);
        $this->assertBomEditable($bom);
        $data = $this->validateBom($request);

        DB::transaction(function () use ($bom, $data) {
            $bom->update([
                'version' => $data['version'],
                'effective_date' => $data['effective_date'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
            $bom->detail()->delete();
            $this->syncBomLines($bom, $data['lines'] ?? []);
        });

        return ApiResponse::item($bom->fresh()->load('detail.item'));
    }

    public function destroyBom(Request $request, int $id)
    {
        $bom = npd_bom_main::findOrFail($id);
        $this->assertBomEditable($bom);

        if (npd_cost_main::where('bom_id', $bom->id)->exists()) {
            throw BizException::make(
                'NPD_BOM_USED',
                'BOM ini dipakai sebuah estimasi biaya. Hapus estimasinya dulu.'
            );
        }

        $bom->detail()->delete();
        $bom->delete();
        AuditLogger::record($request, "Hapus preliminary BOM #{$id}");

        return ApiResponse::item(['message' => 'BOM dihapus.']);
    }

    /* ---------------- estimasi biaya ---------------- */

    public function storeCost(Request $request, int $projectId)
    {
        npd_project::findOrFail($projectId);

        $data = $request->validate([
            'version' => ['required', 'string', 'max:20'],
            'period' => ['required', 'regex:/^\d{6}$/'],
            'bom_id' => ['nullable', 'integer', 'exists:npd_bom_main,id'],
            'overhead' => ['nullable', 'numeric', 'min:0'],
            'margin_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $cost = npd_cost_main::create($data + [
            'main_id' => $projectId,
            'status' => 'DRAFT',
            'user_id' => $request->user()->id,
        ]);

        // Langsung dihitung dari BOM dan routing, supaya versinya tidak lahir kosong.
        $cost = $this->svc->recalculate($cost, $request->user()->id);
        AuditLogger::record($request, "Buat estimasi biaya {$cost->version} proyek #{$projectId}");

        return ApiResponse::item($cost, 201);
    }

    public function updateCost(Request $request, int $id)
    {
        $cost = npd_cost_main::findOrFail($id);
        $this->svc->assertEditable($cost);

        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{6}$/'],
            'bom_id' => ['nullable', 'integer', 'exists:npd_bom_main,id'],
            'overhead' => ['nullable', 'numeric', 'min:0'],
            'margin_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:300'],
            // Baris yang memang diketik orang: tooling & lain-lain.
            'manual_lines' => ['array'],
            'manual_lines.*.cost_type' => ['required', 'in:TOOLING,OTHER'],
            'manual_lines.*.descrip' => ['required', 'string', 'max:150'],
            'manual_lines.*.amount' => ['required', 'numeric', 'min:0'],
            'manual_lines.*.note' => ['nullable', 'string', 'max:150'],
        ]);

        DB::transaction(function () use ($cost, $data) {
            $cost->update($data);
            $cost->detail()->whereIn('cost_type', ['TOOLING', 'OTHER'])->delete();

            foreach ($data['manual_lines'] ?? [] as $l) {
                $cost->detail()->create([
                    'cost_type' => $l['cost_type'],
                    'descrip' => $l['descrip'],
                    'qty' => 1,
                    'rate' => $l['amount'],
                    'amount' => $l['amount'],
                    'note' => $l['note'] ?? null,
                ]);
            }
        });

        return ApiResponse::item($this->svc->recalculate($cost->fresh(), $request->user()->id));
    }

    /** Hitung ulang dari BOM & routing terkini. */
    public function recalculate(Request $request, int $id)
    {
        $cost = npd_cost_main::findOrFail($id);

        return ApiResponse::item($this->svc->recalculate($cost, $request->user()->id));
    }

    public function destroyCost(Request $request, int $id)
    {
        $cost = npd_cost_main::findOrFail($id);
        $this->svc->assertEditable($cost);

        $cost->detail()->delete();
        $cost->delete();
        AuditLogger::record($request, "Hapus estimasi biaya #{$id}");

        return ApiResponse::item(['message' => 'Estimasi biaya dihapus.']);
    }

    /* ---------------- approval quotation ---------------- */

    public function submitCost(Request $request, int $id)
    {
        $cost = npd_cost_main::findOrFail($id);
        $this->svc->assertEditable($cost);

        if ($cost->quoted_price <= 0) {
            throw BizException::make('NPD_QUOTE_ZERO', 'Harga penawaran masih nol — hitung dulu estimasinya.');
        }

        $cost->submitForApproval();
        AuditLogger::record($request, "Ajukan quotation {$cost->version} proyek #{$cost->main_id}");

        return ApiResponse::item($cost->fresh()->load('detail'));
    }

    public function approveCost(Request $request, int $id)
    {
        $cost = npd_cost_main::findOrFail($id);

        if ($cost->status !== 'SUBMITTED') {
            throw BizException::make('NPD_COST_STATE', 'Estimasi ini tidak sedang menunggu persetujuan.');
        }

        app(ApprovalEngine::class)->approve($cost, $request->user(), $request->input('note'));

        return ApiResponse::item($cost->fresh()->load('detail'));
    }

    public function rejectCost(Request $request, int $id)
    {
        $request->validate(['note' => ['required', 'string', 'max:300']]);
        $cost = npd_cost_main::findOrFail($id);

        app(ApprovalEngine::class)->reject($cost, $request->user(), $request->input('note'));

        return ApiResponse::item($cost->fresh()->load('detail'));
    }

    /** Quotation yang sudah disetujui dicatat sebagai harga pelanggan. */
    public function toPricelist(Request $request, int $id)
    {
        $cost = npd_cost_main::findOrFail($id);

        $data = $request->validate([
            'currency_id' => ['nullable', 'integer', 'exists:m_currency,id'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'min_qty' => ['nullable', 'integer', 'min:1'],
        ]);

        $det = $this->svc->toPricelist($cost, $data, $request->user()->id);

        return ApiResponse::item([
            'pricelist_det' => $det,
            'cost' => $cost->fresh(),
            'message' => 'Harga tercatat sebagai pricelist DRAFT — masih perlu approval pricelist.',
        ], 201);
    }

    /* ---------------- helpers ---------------- */

    private function assertBomEditable(npd_bom_main $bom): void
    {
        if ($bom->status !== 'DRAFT') {
            throw BizException::make('NPD_BOM_LOCKED', 'BOM yang sudah disetujui tidak dapat diubah. Buat versi baru.');
        }
    }

    private function syncBomLines(npd_bom_main $bom, array $lines): void
    {
        foreach ($lines as $i => $l) {
            // Satu baris harus menunjuk sesuatu: item master, atau nama part
            // baru. Baris yang tidak menunjuk keduanya tidak bisa dibeli siapa pun.
            if (empty($l['item_id']) && blank($l['new_item_name'] ?? null)) {
                throw BizException::make(
                    'NPD_BOM_LINE',
                    'Baris #'.($i + 1).': pilih item master atau isi nama part barunya.'
                );
            }

            $bom->detail()->create([
                'item_id' => $l['item_id'] ?? null,
                'new_item_code' => $l['new_item_code'] ?? null,
                'new_item_name' => $l['new_item_name'] ?? null,
                /*
                 * Peran baris mengikuti golongan barangnya, bukan pilihan di
                 * layar: material pipa masuk baris RM (dihitung per panjang),
                 * komponen masuk baris PM (dihitung per buah). Membiarkannya
                 * diketik berarti bahan baku bisa masuk sebagai komponen, dan
                 * BOM produksi hasil serah terima akan salah tabel.
                 *
                 * Part yang belum terdaftar belum punya golongan, jadi di situ
                 * pilihan penggunanya yang dipakai.
                 */
                'role' => $this->roleFor($l),
                'qty' => $l['qty'] ?? 1,
                'length_use' => $l['length_use'] ?? null,
                'uom_id' => $l['uom_id'] ?? null,
                'ven_id' => $l['ven_id'] ?? null,
                'unit_cost' => $l['unit_cost'] ?? 0,
                'cost_source' => 'MANUAL',
                'note' => $l['note'] ?? null,
            ]);
        }
    }

    /**
     * Peran baris BOM: RM dihitung per panjang, PM per buah.
     *
     * Barang jadi yang dipakai sebagai sub-rakitan ikut baris PM — ia dikonsumsi
     * per buah, bukan dipotong dari batangan.
     */
    private function roleFor(array $line): string
    {
        if (empty($line['item_id'])) {
            return $line['role'] ?? 'RM';
        }

        $type = DB::table('m_item')->where('id', $line['item_id'])->value('type');

        return $type === 'RM' ? 'RM' : 'PM';
    }

    private function validateBom(Request $request): array
    {
        return $request->validate([
            'version' => ['required', 'string', 'max:20'],
            'effective_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:300'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['nullable', 'integer', 'exists:m_item,id'],
            'lines.*.new_item_code' => ['nullable', 'string', 'max:50'],
            'lines.*.new_item_name' => ['nullable', 'string', 'max:100'],
            'lines.*.role' => ['nullable', 'in:RM,PM'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.001'],
            'lines.*.length_use' => ['nullable', 'numeric', 'min:0'],
            'lines.*.uom_id' => ['nullable', 'integer', 'exists:m_uom,id'],
            'lines.*.ven_id' => ['nullable', 'integer', 'exists:m_contacts,id'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.note' => ['nullable', 'string', 'max:150'],
        ]);
    }
}
