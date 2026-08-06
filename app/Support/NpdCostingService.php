<?php

namespace App\Support;

use App\Exceptions\BizException;
use App\Models\m_item;
use App\Models\m_pricelist_det;
use App\Models\m_pricelist_main;
use App\Models\npd_bom_main;
use App\Models\npd_cost_main;
use App\Models\npd_project;
use Illuminate\Support\Facades\DB;

/**
 * Estimasi biaya part baru, disusun dari angka yang sudah ada di sistem.
 *
 * Tidak ada tarif yang diketik ulang di sini. Harga material datang dari
 * kesepakatan supplier (`m_supplier_item`, lewat MrpService yang juga dipakai
 * MRP), tarif upah dan overhead dari `cst_rate` periode yang dipilih. Estimasi
 * yang tarifnya diketik manual akan selalu berbeda dengan COGM yang dihitung
 * setelah barangnya benar-benar diproduksi — dan selisih itu tidak akan pernah
 * bisa dijelaskan.
 *
 * Yang diketik manual hanya yang memang tidak ada angkanya: tooling, dan
 * overhead di luar tarif proses.
 *
 * PRD_Modul_NPD_FTPI.md §7.4
 */
class NpdCostingService
{
    public function __construct(private MrpService $mrp) {}

    /**
     * Daftarkan part proyek ke master item sebagai barang uji coba.
     *
     * Dilakukan pada fase desain, bukan saat handover: sebuah Work Order trial
     * membutuhkan `fg_id`, dan nomor part sudah diketahui begitu drawing
     * pelanggan diterima. Serah terima nanti yang menaikkannya ke produksi massal.
     */
    public function registerPart(npd_project $project, array $data, int $userId): m_item
    {
        if ($project->item_id) {
            throw BizException::make('NPD_PART_EXISTS', 'Part proyek ini sudah terdaftar di master item.');
        }

        if (m_item::where('code', $data['code'])->exists()) {
            throw BizException::make('NPD_PART_DUP', "Kode item {$data['code']} sudah dipakai.");
        }

        return DB::transaction(function () use ($project, $data) {
            $item = m_item::create([
                'code' => $data['code'],
                'part_name' => $data['part_name'] ?? $project->part_name,
                /*
                 * Golongan barang: RM (bahan baku), PM (komponen), atau FG
                 * (barang jadi). Ini yang menentukan bagaimana part diperlakukan
                 * seluruh sistem — FG direncanakan dan dijual, RM dan PM dibeli
                 * lalu masuk BOM barang lain. Sebelumnya dipaksa FG, padahal
                 * proyek modifikasi dan derivatif sering melahirkan komponen
                 * baru, bukan produk baru.
                 */
                'type' => $data['type'] ?? 'FG',
                'category_id' => $data['category_id'],
                'descrip' => "Dari proyek NPD {$project->code}",
                /*
                 * Kolom dimensi di master item warisan tidak menerima null, dan
                 * pada fase desain sebagian memang belum diketahui. Nol berarti
                 * "belum diisi" — bukan klaim bahwa tebalnya nol milimeter, dan
                 * serah terima nanti yang melengkapinya.
                 */
                'o_d' => $data['o_d'] ?? 0,
                'thick' => $data['thick'] ?? 0,
                'length' => $data['length'] ?? 0,
                'weight' => $data['weight'] ?? 0,
                'tolerance' => $data['tolerance'] ?? '',
                'min_stock' => 0,
                'max_stock' => 0,
                /*
                 * Masih uji coba sampai diserahterimakan. Bukan sekadar penanda:
                 * MPP, forecast, sales order, dan Work Order produksi semuanya
                 * menolak part berstatus TRIAL — jadi part ini boleh dipakai
                 * Work Order uji coba, tetapi tidak bisa direncanakan atau dijual.
                 */
                'lifecycle' => ItemLifecycle::TRIAL,
                'active' => 0,
                'status' => 'DRAFT',
            ]);

            $project->update(['item_id' => $item->id]);
            AuditLogger::record(request(), "Daftarkan part {$item->code} dari proyek {$project->code}", $project->code);

            return $item;
        });
    }

    /**
     * Hitung ulang estimasi dari BOM dan routing yang ada.
     *
     * Baris material dan proses dibuang lalu disusun ulang; baris tooling dan
     * lain-lain dipertahankan karena itu angka yang dimasukkan orang, bukan
     * hasil hitungan.
     */
    public function recalculate(npd_cost_main $cost, int $userId): npd_cost_main
    {
        $this->assertEditable($cost);

        $project = npd_project::findOrFail($cost->main_id);
        $bom = $cost->bom_id
            ? npd_bom_main::with('detail')->find($cost->bom_id)
            : npd_bom_main::with('detail')->where('main_id', $project->id)->orderByDesc('id')->first();

        if (! $bom) {
            throw BizException::make('NPD_NO_BOM', 'Proyek ini belum punya preliminary BOM.');
        }

        return DB::transaction(function () use ($cost, $bom, $project, $userId) {
            $cost->detail()->whereIn('cost_type', ['MATERIAL', 'LABOR', 'FOH'])->delete();

            $material = $this->buildMaterialLines($cost, $bom);
            $process = $this->buildProcessLines($cost, $project);

            $manual = $cost->detail()->whereIn('cost_type', ['TOOLING', 'OTHER'])->get();
            $tooling = (float) $manual->where('cost_type', 'TOOLING')->sum('amount');
            $other = (float) $manual->where('cost_type', 'OTHER')->sum('amount');

            $subtotal = $material + $process + $tooling + $other + (float) $cost->overhead;
            $quoted = round($subtotal * (1 + ((float) $cost->margin_pct / 100)), 2);

            $cost->update([
                'bom_id' => $bom->id,
                'material_cost' => round($material, 2),
                'process_cost' => round($process, 2),
                'tooling_cost' => round($tooling, 2),
                'total_cost' => round($subtotal, 2),
                'quoted_price' => $quoted,
                'user_id' => $cost->user_id ?? $userId,
            ]);

            return $cost->fresh()->load('detail.proc', 'detail.item');
        });
    }

    /**
     * Material: harga dari supplier prioritas, sama seperti yang dipakai MRP.
     *
     * Part yang belum terdaftar memakai harga yang diketik di baris BOM-nya —
     * itu satu-satunya angka yang ada untuk barang yang belum pernah dibeli.
     */
    private function buildMaterialLines(npd_cost_main $cost, npd_bom_main $bom): float
    {
        $total = 0.0;

        foreach ($bom->detail as $line) {
            $unitCost = (float) $line->unit_cost;
            $source = $line->cost_source;

            if ($line->item_id) {
                $terms = $this->mrp->supplierTerms((int) $line->item_id);
                if ($terms['price'] > 0) {
                    $unitCost = (float) $terms['price'];
                    $source = $terms['source'];      // SUPPLIER atau ITEM
                }
            }

            $amount = round($line->qty * $unitCost, 2);
            $total += $amount;

            $cost->detail()->create([
                'cost_type' => 'MATERIAL',
                'item_id' => $line->item_id,
                'descrip' => $line->label(),
                'qty' => $line->qty,
                'rate' => $unitCost,
                'amount' => $amount,
                'note' => $source === 'SUPPLIER'
                    ? 'harga supplier prioritas'
                    : ($source === 'ITEM' ? 'harga pembelian rata-rata' : 'harga diisi manual'),
            ]);

            // Sumber harga ikut disimpan di BOM supaya terlihat mana yang masih
            // tebakan dan mana yang sudah berdasar kesepakatan supplier.
            $line->update(['unit_cost' => $unitCost, 'cost_source' => $source]);
        }

        return $total;
    }

    /**
     * Upah dan overhead: cycle time routing × tarif per jam periode itu.
     *
     * Routing diambil dari part yang sudah didaftarkan. Selama part belum
     * terdaftar atau cycle time-nya belum ada, biaya proses nol — dan itu
     * memang jawaban yang jujur, bukan angka karangan yang terlihat rapi.
     */
    private function buildProcessLines(npd_cost_main $cost, npd_project $project): float
    {
        if (! $project->item_id) {
            return 0.0;
        }

        $routes = DB::table('m_route_time as rt')
            ->join('m_process as p', 'p.id', '=', 'rt.proc_id')
            ->where('rt.item_id', $project->item_id)
            ->where('rt.active', 1)
            ->orderBy('rt.priority')
            ->get(['rt.proc_id', 'rt.cycle_sec', 'rt.setup_min', 'p.name_p']);

        if ($routes->isEmpty()) {
            return 0.0;
        }

        $rates = DB::table('cst_rate')
            ->where('period', $cost->period)
            ->get()
            ->groupBy('process_id');

        $total = 0.0;

        foreach ($routes as $r) {
            foreach (['LABOR', 'FOH'] as $type) {
                $rate = (float) ($rates[$r->proc_id] ?? collect())
                    ->firstWhere('rate_type', $type)?->rate_per_hour;

                if ($rate <= 0) {
                    continue;
                }

                $hours = (float) $r->cycle_sec / 3600;
                $amount = round($hours * $rate, 2);

                if ($amount <= 0) {
                    continue;
                }

                $total += $amount;

                $cost->detail()->create([
                    'cost_type' => $type,
                    'proc_id' => $r->proc_id,
                    'descrip' => $r->name_p,
                    'qty' => 1,
                    'cycle_sec' => $r->cycle_sec,
                    'rate' => $rate,
                    'amount' => $amount,
                    'note' => "tarif {$cost->period}",
                ]);
            }
        }

        return $total;
    }

    /**
     * Jadikan quotation sebagai pricelist pelanggan.
     *
     * Hanya estimasi yang sudah APPROVED (keputusan 4 Agustus 2026), dan
     * hasilnya tetap DRAFT yang melewati approval pricelist yang sudah ada —
     * harga yang langsung berlaku tanpa disetujui siapa pun adalah harga yang
     * tidak ada yang bertanggung jawab atasnya.
     */
    public function toPricelist(npd_cost_main $cost, array $data, int $userId): m_pricelist_det
    {
        if ($cost->status !== 'APPROVED') {
            throw BizException::make(
                'NPD_QUOTE_NOT_APPROVED',
                'Hanya quotation yang sudah disetujui yang boleh dijadikan pricelist.'
            );
        }

        if ($cost->pricelist_det_id) {
            throw BizException::make('NPD_QUOTE_USED', 'Quotation ini sudah dijadikan pricelist.');
        }

        $project = npd_project::findOrFail($cost->main_id);

        if (! $project->item_id) {
            throw BizException::make(
                'NPD_NO_PART',
                'Part proyek ini belum terdaftar di master item, jadi harganya belum bisa dicatat.'
            );
        }

        return DB::transaction(function () use ($cost, $project, $data, $userId) {
            // Satu pricelist DRAFT per pelanggan dipakai ulang, supaya tidak
            // lahir puluhan header berisi satu baris.
            $main = m_pricelist_main::firstOrCreate(
                ['cus_id' => $project->cus_id, 'status' => 'DRAFT'],
                [
                    'code' => app(NumberingService::class)->next('PRICELIST', 'PL'),
                    'user_id' => $userId,
                ]
            );

            $det = m_pricelist_det::create([
                'main_id' => $main->id,
                'item_id' => $project->item_id,
                'price' => $cost->quoted_price,
                // Kolomnya tidak menerima null; tanpa pilihan, harga dicatat
                // dalam mata uang dasar seperti pricelist lainnya.
                'currency_id' => $data['currency_id']
                    ?? DB::table('m_currency')->where('is_base', 1)->value('id')
                    ?? DB::table('m_currency')->value('id'),
                'valid_from' => $data['valid_from'] ?? now()->toDateString(),
                /*
                 * Kolomnya tidak menerima null, jadi "tanpa batas" ditulis
                 * sebagai tanggal yang jauh — bukan hari ini, yang akan membuat
                 * harganya kedaluwarsa pada hari ia dibuat.
                 */
                'valid_to' => $data['valid_to'] ?? now()->addYears(5)->toDateString(),
                'min_qty' => $data['min_qty'] ?? 1,
            ]);

            $cost->update(['pricelist_det_id' => $det->id]);

            AuditLogger::record(
                request(),
                "Quotation {$cost->version} proyek {$project->code} → pricelist {$main->code}",
                $project->code
            );

            return $det;
        });
    }

    /** Estimasi yang sudah diajukan atau disetujui tidak boleh dihitung ulang. */
    public function assertEditable(npd_cost_main $cost): void
    {
        if (! in_array($cost->status, ['DRAFT', 'REJECTED'], true)) {
            throw BizException::make(
                'NPD_COST_LOCKED',
                'Estimasi yang sudah diajukan atau disetujui tidak dapat diubah. Buat versi baru.'
            );
        }
    }
}
