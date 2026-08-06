<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Perbaiki status Delivery Order yang tidak dikenal alur kerjanya.
 *
 * Kosakata DO adalah DRAFT → SHIPPED → RECEIVED, ditegakkan `DoController`:
 * `ship()` menuntut DRAFT, `receive()` menuntut SHIPPED, dan pembatalan hanya
 * dari DRAFT. Sebuah status "DELIVERED" pernah masuk lewat seeder — tidak
 * pernah dihasilkan maupun diterima kode mana pun.
 *
 * Akibatnya DO yang menyandangnya terkunci selamanya: tidak bisa dikirim,
 * tidak bisa ditandai diterima, tidak bisa dibatalkan. Dan saat truk yang
 * memuatnya ditandai tiba, `ShippingOrderController::deliver()` hanya mengubah
 * DO berstatus SHIPPED — DO ini tertinggal di limbo sementara pengirimannya
 * sudah dinyatakan selesai.
 *
 * DELIVERED paling dekat artinya dengan RECEIVED: barangnya sudah sampai.
 */
return new class extends Migration
{
    public function up(): void
    {
        $n = DB::table('sls_do_main')->where('status', 'DELIVERED')->update(['status' => 'RECEIVED']);

        if ($n) {
            info("Status Delivery Order diperbaiki: {$n} baris DELIVERED → RECEIVED");
        }
    }

    public function down(): void
    {
        // Sengaja tidak dikembalikan: DELIVERED bukan status yang sah.
    }
};
