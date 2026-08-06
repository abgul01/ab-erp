<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bentuk material dipisahkan dari golongan barang.
 *
 * Selama ini keduanya berebut satu kolom. `m_item.type` menyimpan golongan
 * (RM / PM / FG / CONSUMABLE) — itulah yang dibaca dashboard, dipakai NPD untuk
 * menentukan baris BOM, dan menjadi arti "Material atau FG". Tetapi layar Item
 * Master menuliskan **bentuk** ke kolom yang sama: Pipe, Roundbar, Square Pipe.
 *
 * Akibatnya dua-duanya rusak. Menyimpan satu item lewat layar itu mengubah
 * golongannya menjadi "Roundbar", sehingga ia berhenti terhitung sebagai bahan
 * baku di dashboard dan salah tabel saat BOM-nya disalin. Sementara pemilih
 * Group-nya sendiri kosong karena mencari kategori bernama "Material"/"FG" yang
 * tidak pernah ada — jadi item bahkan tidak bisa disimpan sama sekali.
 *
 * Sejak sekarang: `type` tetap golongan, dan bentuk punya kolomnya sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_item', function (Blueprint $table) {
            // Pipe | Roundbar | Square Pipe | Square Bar | Plat Bar | Other
            $table->string('shape', 20)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('m_item', fn (Blueprint $t) => $t->dropColumn('shape'));
    }
};
