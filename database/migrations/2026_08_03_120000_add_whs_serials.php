<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Serial penerimaan untuk sparepart & barang habis pakai.
 *
 * Alat sudah punya nomor per unit karena ia kembali. Sparepart dan barang habis
 * pakai tidak kembali, tapi tetap perlu identitas: satu penerimaan bisa berisi
 * bearing dari kiriman Juli dan bearing dari kiriman Agustus, dan ketika salah
 * satunya membuat mesin berhenti dua minggu kemudian, pertanyaannya selalu
 * "yang dipasang itu batch yang mana".
 *
 * Karena itu serial melekat pada baris penerimaan — satu baris satu serial,
 * jadi satu dokumen penerimaan menghasilkan sebanyak variasi barangnya. Baris
 * pengeluaran menunjuk serial yang diambil, dan dari situ downtime mesin
 * (tr_dt_cut_detail.serial_item / tr_dt_pro_detail.serial_tool — kolom yang
 * sudah lama ada tapi selama ini diisi ketikan bebas) akhirnya menunjuk barang
 * yang benar-benar pernah diterima.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whs_inc_det', function (Blueprint $table) {
            // Unik: serial adalah identitas satu batch penerimaan.
            $table->string('serial_code', 40)->nullable()->unique()->after('item_id');
        });

        Schema::table('whs_out_det', function (Blueprint $table) {
            // Batch yang diambil. Kosong untuk alat — alat memakai nomor unit.
            $table->string('serial_code', 40)->nullable()->index()->after('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('whs_inc_det', function (Blueprint $table) {
            $table->dropUnique(['serial_code']);
            $table->dropColumn('serial_code');
        });

        Schema::table('whs_out_det', fn (Blueprint $table) => $table->dropColumn('serial_code'));
    }
};
