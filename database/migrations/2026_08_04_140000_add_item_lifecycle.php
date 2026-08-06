<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Siklus hidup part: mana yang boleh diproduksi massal, mana yang masih diuji.
 *
 * Sebelum ini, part hasil proyek NPD dibedakan hanya lewat `active = 0` — sebuah
 * kesepakatan yang tidak ditegakkan siapa pun. Akibatnya nyata dan bisa
 * ditunjukkan: rencana bulanan (MPP) menerima part trial tanpa keberatan, MRP
 * menghitung Work Order trial sebagai pasokan siap jual, dan part trial muncul
 * di semua pemilih item di seluruh aplikasi.
 *
 * Yang dipisahkan bukan tabelnya, melainkan statusnya. Satu part harus punya
 * satu identitas seumur hidupnya: lot trial, hasil inspeksi, dan Work Order
 * uji cobanya harus tetap menunjuk baris yang sama setelah ia naik ke produksi
 * massal — pertanyaan pertama auditor PPAP justru "tunjukkan data trial part
 * ini", dan tautan itu putus kalau part-nya disalin ke tabel lain dengan id baru.
 *
 *   TRIAL    — masih diuji. Tidak boleh direncanakan, dijual, atau dipesan.
 *   MASSPRO  — sudah lulus dan boleh diproduksi massal.
 *   OBSOLETE — tidak dibuat lagi, tetapi riwayatnya tetap ada.
 *
 * `active` tetap dengan artinya yang lama ("dipakai atau tidak"); `lifecycle`
 * menjawab pertanyaan yang berbeda ("sudah boleh diproduksi massal atau belum").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_item', function (Blueprint $table) {
            $table->string('lifecycle', 10)->default('MASSPRO')->after('type')->index();
        });

        /*
         * Seluruh part yang sudah ada memang sudah diproduksi — itulah kenapa
         * ia ada di master. Kecuali part yang lahir dari proyek NPD dan belum
         * diserahterimakan; itu yang masih trial.
         */
        if (Schema::hasTable('npd_project')) {
            DB::table('m_item')
                ->whereIn('id', DB::table('npd_project')
                    ->whereNotNull('item_id')
                    ->whereNotIn('status', ['CLOSED'])
                    ->select('item_id'))
                ->update(['lifecycle' => 'TRIAL']);
        }

        Schema::table('prd_wo_main', function (Blueprint $table) {
            /*
             * Work Order uji coba dibedakan dari Work Order produksi. Tanpa ini,
             * MRP menghitung keluaran trial sebagai barang yang siap memenuhi
             * permintaan pelanggan — padahal ia dibuat justru untuk dibongkar,
             * diukur, dan sebagian dikirim sebagai sampel PPAP.
             */
            $table->string('wo_kind', 12)->default('PROD')->after('code')->index();
            $table->unsignedBigInteger('npd_project_id')->nullable()->after('wo_kind');
        });
    }

    public function down(): void
    {
        Schema::table('m_item', fn (Blueprint $t) => $t->dropColumn('lifecycle'));
        Schema::table('prd_wo_main', fn (Blueprint $t) => $t->dropColumn(['wo_kind', 'npd_project_id']));
    }
};
