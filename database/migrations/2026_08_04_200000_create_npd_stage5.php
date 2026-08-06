<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NPD Tahap 5 — PPAP (PRD §7.6).
 *
 * PPAP adalah paket bukti yang dikirim ke pelanggan sebelum produksi massal
 * diizinkan. Delapan belas elemennya baku menurut AIAG, jadi ia master yang
 * di-seed — bukan daftar yang diketik ulang tiap submission. Yang berbeda
 * antar-submission hanyalah level PPAP-nya, dan level itulah yang menentukan
 * elemen mana yang wajib.
 *
 * Elemen boleh ditandai tidak berlaku (NA) — Appearance Approval Report memang
 * tidak masuk akal untuk part yang tidak punya persyaratan penampilan — tetapi
 * NA menuntut alasan tertulis, sama seperti deliverable yang dikecualikan.
 * Sebutan "tidak berlaku" tanpa alasan adalah cara paling halus melewatkan
 * bukti yang sebenarnya diminta.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Master 18 elemen PPAP ----
        Schema::create('npd_ppap_std', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('element_no')->unique();   // 1..18
            $table->string('name', 120);
            $table->string('descrip', 250)->nullable();
            /*
             * Level PPAP yang mewajibkan elemen ini, dipisah koma (mis. "3,5").
             * Level 4 sengaja jarang muncul: isinya ditentukan pelanggan, jadi
             * yang wajib hanyalah PSW.
             */
            $table->string('level_required', 20)->default('3,5');
            $table->timestamps();
        });

        Schema::create('npd_ppap_main', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();        // npd_project
            $table->string('code', 50)->unique();
            $table->unsignedTinyInteger('ppap_level')->default(3); // 1..5
            $table->string('psw_no', 50)->nullable();              // Part Submission Warrant
            $table->date('submission_date')->nullable();
            // DRAFT | SUBMITTED | INTERIM | APPROVED | REJECTED
            $table->string('status', 10)->default('DRAFT');
            $table->date('approval_date')->nullable();
            $table->string('customer_pic', 60)->nullable();
            $table->string('note', 400)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['main_id', 'status']);
        });

        Schema::create('npd_ppap_det', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();        // npd_ppap_main
            $table->unsignedBigInteger('std_id')->index();         // npd_ppap_std
            $table->string('status', 6)->default('OPEN');          // OPEN | DONE | NA
            $table->unsignedBigInteger('doc_id')->nullable();      // npd_doc sebagai bukti
            // Terisi bila status ditentukan sistem dari data proyek, bukan tangan.
            $table->string('auto_source', 40)->nullable();
            $table->string('note', 250)->nullable();
            $table->timestamps();

            $table->unique(['main_id', 'std_id']);
        });
    }

    public function down(): void
    {
        foreach (['npd_ppap_det', 'npd_ppap_main', 'npd_ppap_std'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
