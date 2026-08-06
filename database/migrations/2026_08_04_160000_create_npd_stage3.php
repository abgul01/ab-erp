<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NPD Tahap 3 — trial produksi dan hasil ukurnya (PRD §7.5, §12).
 *
 * Setiap trial wajib punya Work Order sendiri (keputusan 4 Agustus 2026). Itu
 * bukan formalitas: lewat WO-lah pemakaian material, jam mesin, dan hasil MES
 * tercatat di jalur yang sama dengan produksi biasa — kalau trial dicatat di
 * luar itu, biayanya hilang dan operator harus mengetik dua kali. WO-nya
 * ditandai `wo_kind = NPD_TRIAL` supaya keluarannya tidak dihitung MRP sebagai
 * pasokan siap jual.
 *
 * Hasil ukur menunjuk `m_inspection_param`, bukan teks bebas. Karakteristik yang
 * diketik sebagai kalimat tidak bisa dibandingkan antar-trial, tidak bisa
 * dijadikan dasar control plan, dan tidak bisa jadi parameter QC produksi nanti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('npd_trial_main', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();       // npd_project
            $table->unsignedBigInteger('phase_id')->nullable();   // npd_project_phase
            // Wajib: tidak ada trial tanpa Work Order-nya sendiri.
            $table->unsignedBigInteger('wo_id')->index();
            $table->string('code', 50)->unique();
            // PROTOTYPE | PILOT | MASS_TRIAL
            $table->string('trial_type', 12)->default('PROTOTYPE');
            $table->date('date');
            $table->unsignedBigInteger('machine_id')->nullable();
            $table->integer('planned_qty')->default(0);
            $table->integer('produced_qty')->default(0);
            $table->integer('ok_qty')->default(0);
            $table->integer('ng_qty')->default(0);
            $table->string('conclusion', 400)->nullable();
            $table->string('status', 10)->default('DRAFT');       // DRAFT | DONE
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['main_id', 'trial_type']);
        });

        Schema::create('npd_trial_det', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();       // npd_trial_main
            $table->unsignedBigInteger('param_id')->index();      // m_inspection_param
            // Nomor benda uji: satu parameter diukur pada beberapa potong.
            $table->unsignedSmallInteger('sample_no')->default(1);
            /*
             * Spesifikasi dibekukan di baris hasil. Kalau hanya menunjuk master,
             * toleransi yang direvisi tahun depan akan diam-diam mengubah arti
             * hasil ukur yang sudah terjadi — dan trial yang dulu lulus bisa
             * berubah jadi gagal tanpa ada yang mengukur ulang apa pun.
             */
            $table->decimal('nominal', 12, 4)->nullable();
            $table->decimal('min_value', 12, 4)->nullable();
            $table->decimal('max_value', 12, 4)->nullable();
            $table->decimal('measured', 12, 4);
            // OK | NG — dihitung server dari batas di atas, tidak pernah diketik.
            $table->string('judgement', 2);
            $table->string('instrument', 50)->nullable();
            $table->unsignedBigInteger('inspector_id')->nullable();
            $table->string('note', 150)->nullable();

            $table->index(['main_id', 'param_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('npd_trial_det');
        Schema::dropIfExists('npd_trial_main');
    }
};
