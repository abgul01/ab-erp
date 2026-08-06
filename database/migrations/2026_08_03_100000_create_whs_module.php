<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WHS Tools — gudang non-material dengan master, pembelian, dan stok sendiri.
 *
 * Barang yang dipakai untuk menjalankan pabrik bukan barang yang dijual: mata
 * bor, sarung tangan, seal pompa, kunci momen. Sebelumnya semuanya menumpang
 * `m_item` bersama bahan baku dan barang jadi — dan tidak pernah benar-benar
 * jalan, karena layar General Store mencari `type` 'SM'/'CN' yang tidak pernah
 * ada isinya. Jadi gudang ini berdiri sendiri: master, PO, penerimaan,
 * pengeluaran, dan perhitungan stoknya terpisah dari alur produksi.
 *
 * Tiga jenis barang, dan perbedaannya nyata, bukan sekadar label:
 *
 *   PART       — sparepart mesin. Keluar sekali, dipasang, jadi beban.
 *   CONSUMABLE — habis pakai. Keluar sekali, jadi beban.
 *   TOOL       — alat kerja. Dipinjam, lalu kembali. Karena itu tiap unitnya
 *                dilacak satu per satu: pertanyaan "kunci momen nomor 3 ada di
 *                siapa" tidak bisa dijawab oleh angka jumlah.
 *
 * Itu pula sebabnya pengeluaran TOOL tidak membebani biaya: alatnya masih milik
 * perusahaan sampai rusak atau hilang, dan pembebanannya menunggu saat itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Master barang WHS ----
        Schema::create('m_whs_item', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->string('whs_type', 12);              // PART | CONSUMABLE | TOOL
            $table->string('categ', 40)->nullable();     // mis. Cutting Tool, APD, Bearing
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->string('brand', 40)->nullable();
            $table->string('spec', 150)->nullable();
            $table->string('rack_loc', 30)->nullable();  // lokasi rak di gudang WHS
            $table->integer('min_stock')->default(0);
            $table->integer('max_stock')->default(0);
            // Harga acuan untuk barang yang belum pernah diterima sama sekali.
            $table->decimal('standard_cost', 18, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['whs_type', 'active']);
        });

        // ---- Purchase Order WHS ----
        Schema::create('whs_po_main', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->date('date');
            $table->unsignedBigInteger('ven_id');
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->decimal('rate', 18, 6)->default(1);
            $table->unsignedSmallInteger('top_days')->default(0);
            $table->date('eta')->nullable();
            // DRAFT | SUBMITTED | APPROVED | CLOSE | CANCELLED
            $table->string('status', 20)->default('DRAFT');
            $table->string('note', 300)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'date']);
        });

        Schema::create('whs_po_det', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();
            $table->unsignedBigInteger('item_id')->index();
            $table->integer('qty');
            $table->integer('qty_received')->default(0);
            $table->decimal('price', 18, 2)->default(0);
            $table->string('note', 150)->nullable();
        });

        // ---- Penerimaan ----
        Schema::create('whs_inc_main', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->date('date');
            // Boleh tanpa PO: saldo awal dan pembelian kecil tunai tetap harus
            // masuk stok, dan menolaknya hanya membuat orang mencatat di luar sistem.
            $table->unsignedBigInteger('po_id')->nullable()->index();
            $table->unsignedBigInteger('ven_id')->nullable();
            $table->string('do_no', 50)->nullable();     // nomor surat jalan supplier
            $table->string('note', 300)->nullable();
            $table->string('status', 20)->default('DRAFT'); // DRAFT | POSTED
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('whs_inc_det', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();
            $table->unsignedBigInteger('po_det_id')->nullable()->index();
            $table->unsignedBigInteger('item_id')->index();
            $table->integer('qty');
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->string('note', 150)->nullable();
        });

        // ---- Pengeluaran ----
        Schema::create('whs_out_main', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->date('date');
            $table->string('dept', 50)->nullable();
            $table->string('receiver', 60)->nullable();
            $table->string('cost_center', 40)->nullable();
            $table->string('note', 300)->nullable();
            $table->string('status', 20)->default('DRAFT'); // DRAFT | POSTED
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('whs_out_det', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();
            $table->unsignedBigInteger('item_id')->index();
            $table->integer('qty');
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->string('cost_center', 40)->nullable();
            $table->unsignedBigInteger('machine_id')->nullable();
            $table->unsignedBigInteger('asset_id')->nullable();
            // Alat yang dipinjam ditunjuk satu per satu; berapa yang sudah kembali
            // dihitung di sini supaya sisa pinjaman terlihat tanpa menghitung ulang.
            $table->integer('qty_returned')->default(0);
            $table->string('note', 150)->nullable();
        });

        // ---- Unit alat, dilacak satu per satu ----
        Schema::create('whs_tool_unit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id')->index();
            $table->string('code', 50)->unique();        // mis. TL-0007#003
            $table->unsignedBigInteger('inc_det_id')->nullable();
            // IN_STOCK | ON_LOAN | DAMAGED | LOST | SCRAPPED
            $table->string('status', 12)->default('IN_STOCK');
            $table->string('holder', 60)->nullable();    // siapa yang memegang saat ini
            $table->unsignedBigInteger('out_det_id')->nullable();
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->string('note', 150)->nullable();
            $table->timestamps();

            $table->index(['item_id', 'status']);
        });

        // ---- Pengembalian alat ----
        Schema::create('whs_ret_main', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->date('date');
            $table->string('returner', 60)->nullable();
            $table->string('note', 300)->nullable();
            $table->string('status', 20)->default('DRAFT'); // DRAFT | POSTED
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('whs_ret_det', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();
            $table->unsignedBigInteger('out_det_id')->nullable()->index();
            $table->unsignedBigInteger('tool_unit_id')->index();
            $table->string('condition', 10)->default('GOOD'); // GOOD | DAMAGED | LOST
            $table->string('note', 150)->nullable();
        });

        /*
         * General Store lama dibuang seluruhnya, sesuai keputusan: item-nya
         * menumpang master produksi dan pemilihnya menyaring tipe yang tidak
         * pernah ada, jadi tidak ada data yang layak dipindahkan.
         */
        foreach (['wh_gen_out_det', 'wh_gen_out_main', 'wh_gen_req_det', 'wh_gen_req_main'] as $t) {
            Schema::dropIfExists($t);
        }
    }

    public function down(): void
    {
        foreach ([
            'whs_ret_det', 'whs_ret_main', 'whs_tool_unit',
            'whs_out_det', 'whs_out_main', 'whs_inc_det', 'whs_inc_main',
            'whs_po_det', 'whs_po_main', 'm_whs_item',
        ] as $t) {
            Schema::dropIfExists($t);
        }

        // Tabel General Store lama tidak dibangun ulang: modulnya sudah diganti.
    }
};
