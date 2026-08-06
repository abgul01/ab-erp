<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NPD Tahap 2 — Preliminary BOM dan estimasi biaya (PRD §7.4, §12).
 *
 * Dua hal yang membedakan BOM di sini dari BOM produksi:
 *
 *   Barisnya boleh menunjuk part yang belum ada. Pada fase desain, sebagian
 *   material memang belum terdaftar di master; memaksa semuanya sudah ada
 *   berarti orang akan mendaftarkan part karangan hanya supaya bisa menyimpan.
 *
 *   Ia belum mengikat produksi. Baru saat handover (tahap 6) isinya disalin
 *   menjadi `m_bom` yang sesungguhnya.
 *
 * Estimasi biaya sengaja menyimpan rinciannya, bukan hanya totalnya: pertanyaan
 * "kenapa harga kita kalah" tidak bisa dijawab oleh satu angka. Tarif proses
 * diambil dari `cst_rate` periode yang dipilih dan **dibekukan** di baris —
 * quotation yang dikirim bulan lalu tidak boleh berubah nilainya karena tarif
 * bulan ini naik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('npd_bom_main', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();      // npd_project
            $table->string('version', 20)->default('v1');
            // DRAFT | APPROVED | SUPERSEDED
            $table->string('status', 12)->default('DRAFT');
            $table->date('effective_date')->nullable();
            $table->string('note', 300)->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->unique(['main_id', 'version']);
        });

        Schema::create('npd_bom_det', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();      // npd_bom_main
            // Kosong berarti part baru yang belum terdaftar di master.
            $table->unsignedBigInteger('item_id')->nullable()->index();
            $table->string('new_item_code', 50)->nullable();
            $table->string('new_item_name', 100)->nullable();
            $table->string('role', 4)->default('RM');            // RM | PM
            $table->decimal('qty', 12, 3)->default(1);
            // Panjang terpakai per pcs, untuk material pipa (mm).
            $table->decimal('length_use', 12, 2)->nullable();
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->unsignedBigInteger('ven_id')->nullable();
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->string('cost_source', 12)->default('MANUAL'); // SUPPLIER | ITEM | MANUAL
            $table->string('note', 150)->nullable();
        });

        Schema::create('npd_cost_main', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();      // npd_project
            $table->unsignedBigInteger('bom_id')->nullable();    // BOM yang dipakai
            $table->string('version', 20)->default('v1');
            // Periode tarif yang dipakai; tarif itu sendiri dibekukan per baris.
            $table->char('period', 6);
            $table->decimal('material_cost', 18, 2)->default(0);
            $table->decimal('process_cost', 18, 2)->default(0);
            $table->decimal('tooling_cost', 18, 2)->default(0);
            $table->decimal('overhead', 18, 2)->default(0);
            $table->decimal('margin_pct', 6, 2)->default(0);
            $table->decimal('total_cost', 18, 2)->default(0);
            $table->decimal('quoted_price', 18, 2)->default(0);
            // DRAFT | SUBMITTED | APPROVED | REJECTED
            $table->string('status', 12)->default('DRAFT');
            $table->unsignedBigInteger('approved_by')->nullable();
            /*
             * Terisi setelah quotation yang APPROVED dijadikan pricelist —
             * sekaligus penanda bahwa satu estimasi tidak dijadikan pricelist
             * dua kali.
             */
            $table->unsignedBigInteger('pricelist_det_id')->nullable();
            $table->string('note', 300)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->unique(['main_id', 'version']);
        });

        Schema::create('npd_cost_det', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();      // npd_cost_main
            // MATERIAL | LABOR | FOH | TOOLING | OTHER
            $table->string('cost_type', 10);
            $table->unsignedBigInteger('proc_id')->nullable();   // m_process, untuk LABOR/FOH
            $table->unsignedBigInteger('item_id')->nullable();   // m_item, untuk MATERIAL
            $table->string('descrip', 150)->nullable();
            $table->decimal('qty', 12, 3)->default(1);
            $table->decimal('cycle_sec', 10, 2)->nullable();
            // Tarif dibekukan di sini; sumbernya boleh berubah nanti.
            $table->decimal('rate', 18, 4)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('note', 150)->nullable();
        });
    }

    public function down(): void
    {
        foreach (['npd_cost_det', 'npd_cost_main', 'npd_bom_det', 'npd_bom_main'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
