<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product Family dan Production Line (PRD §4.1, §4.5).
 *
 * Dua pengelompokan yang selama ini hanya ada di kepala orang.
 *
 * **Product family** menyatukan part yang secara komersial satu keluarga —
 * bracket 42 mm generasi 1, 2, dan turunannya. Manajemen tidak bertanya "berapa
 * margin BRK-TUBE-42-G2"; yang ditanyakan adalah "keluarga bracket ini
 * menguntungkan atau tidak". Tanpa pengelompokan, jawabannya harus dijumlahkan
 * tangan dari daftar seratus baris.
 *
 * **Production line** menyatukan mesin yang bekerja sebagai satu lintasan.
 * Kapasitas dinilai per lintasan, bukan per mesin: satu mesin menganggur di
 * lintasan yang penuh tidak berarti apa-apa kalau ia tidak bisa mengambil alih
 * pekerjaan mesin sebelahnya.
 *
 * Keduanya sengaja nullable. Memaksa seluruh master lama diisi hanya akan
 * membuat orang mengisi asal supaya bisa menyimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_product_family', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->string('descrip', 200)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('m_production_line', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->string('descrip', 200)->nullable();
            // Jam kerja lintasan per hari; dipakai penilaian kapasitas gabungan.
            $table->decimal('daily_hours', 6, 2)->default(16);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('m_item', function (Blueprint $table) {
            $table->unsignedBigInteger('family_id')->nullable()->after('category_id')->index();
        });

        Schema::table('m_machine', function (Blueprint $table) {
            $table->unsignedBigInteger('line_id')->nullable()->after('categ')->index();
        });
    }

    public function down(): void
    {
        Schema::table('m_machine', fn (Blueprint $t) => $t->dropColumn('line_id'));
        Schema::table('m_item', fn (Blueprint $t) => $t->dropColumn('family_id'));
        Schema::dropIfExists('m_production_line');
        Schema::dropIfExists('m_product_family');
    }
};
