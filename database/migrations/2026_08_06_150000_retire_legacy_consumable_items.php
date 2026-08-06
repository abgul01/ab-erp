<?php

use App\Support\ItemLifecycle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pensiunkan consumable yang tertinggal di master item produksi.
 *
 * Sejak modul WHS Tools dibangun, sparepart dan barang habis pakai punya
 * masternya sendiri. Baris bergolongan CONSUMABLE di `m_item` adalah sisa
 * desain sebelumnya — dan ia bukan sekadar sampah yang menganggur:
 *
 *   Golongannya di luar RM/PM/FG, padahal layar Item Master hanya bisa
 *   menghasilkan ketiganya. Membuka lalu menyimpannya mengubah pisau gergaji
 *   menjadi bahan baku, diam-diam.
 *
 *   Siklus hidupnya MASSPRO, sehingga ia lolos seluruh penjagaan dan bisa masuk
 *   rencana bulanan, forecast, maupun sales order.
 *
 * Baris ini tidak dihapus — menghapus master adalah tindakan yang tidak bisa
 * dibatalkan, dan keputusannya milik pemilik data. Yang dilakukan di sini
 * adalah menutup jalannya: OBSOLETE dan non-aktif, sehingga tidak lagi
 * ditawarkan pemilih item mana pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        $n = DB::table('m_item')
            ->where('type', 'CONSUMABLE')
            ->update([
                'lifecycle' => ItemLifecycle::OBSOLETE,
                'active' => 0,
                'updated_at' => now(),
            ]);

        if ($n) {
            info("Consumable warisan dipensiunkan dari master item: {$n} baris → OBSOLETE & non-aktif");
        }
    }

    public function down(): void
    {
        DB::table('m_item')
            ->where('type', 'CONSUMABLE')
            ->update(['lifecycle' => ItemLifecycle::MASSPRO, 'active' => 1]);
    }
};
