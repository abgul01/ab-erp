<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NPD Tahap 4 — FMEA dan Control Plan (PRD §7.5 FR-16, FR-17).
 *
 * FMEA menjawab "apa yang bisa salah, seberapa parah, seberapa sering, dan
 * apakah kita akan mengetahuinya". Control Plan menjawab "lalu apa yang kita
 * ukur di lantai produksi supaya itu ketahuan". Keduanya sepasang: control plan
 * yang tidak lahir dari FMEA hanya daftar pengukuran yang kebetulan terpikirkan.
 *
 * Dua hal yang dijaga skema ini.
 *
 * RPN tidak punya kolom yang bisa diketik bebas artinya — ia disimpan, tetapi
 * selalu ditulis ulang server sebagai S × O × D. RPN yang bisa diketik akan
 * diketik lebih rendah daripada seharusnya begitu ada yang ingin melewatkan
 * tindakan perbaikan.
 *
 * Baris control plan menunjuk `m_process` dan `m_inspection_param`, bukan teks
 * bebas. Itu yang membuatnya bisa berubah menjadi `m_item_inspection` saat
 * serah terima — karakteristik yang ditulis sebagai kalimat berhenti di kertas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('npd_fmea_main', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();       // npd_project
            $table->string('fmea_type', 8);                       // DESIGN | PROCESS
            $table->string('code', 50)->unique();
            $table->string('revision', 10)->default('rev A');
            $table->string('team', 200)->nullable();
            $table->date('date');
            /*
             * Di atas ambang ini, sebuah risiko wajib punya tindakan perbaikan.
             * Disimpan per dokumen karena pelanggan berbeda memakai ambang
             * berbeda; 100 adalah kelaziman industri otomotif.
             */
            $table->unsignedSmallInteger('rpn_threshold')->default(100);
            $table->string('status', 10)->default('DRAFT');       // DRAFT | FINAL
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['main_id', 'fmea_type']);
        });

        Schema::create('npd_fmea_det', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();       // npd_fmea_main
            $table->unsignedBigInteger('proc_id')->nullable();    // m_process, untuk PFMEA
            $table->string('item_function', 150);
            $table->string('failure_mode', 150);
            $table->string('effect', 200);
            $table->unsignedTinyInteger('severity');              // 1..10
            $table->string('cause', 200);
            $table->unsignedTinyInteger('occurrence');            // 1..10
            $table->string('current_control', 200)->nullable();
            $table->unsignedTinyInteger('detection');             // 1..10
            // Selalu hasil hitungan S × O × D; disimpan agar bisa diurutkan & disaring.
            $table->unsignedSmallInteger('rpn');
            $table->string('recommended_action', 300)->nullable();
            $table->string('action_taken', 300)->nullable();
            $table->unsignedBigInteger('resp_user_id')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 10)->default('OPEN');        // OPEN | DONE
            $table->timestamps();

            $table->index(['main_id', 'rpn']);
        });

        Schema::create('npd_cp_main', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();       // npd_project
            $table->string('code', 50)->unique();
            $table->string('revision', 10)->default('rev A');
            // PROTOTYPE | PRE_LAUNCH | PRODUCTION — tiga tahap control plan APQP.
            $table->string('cp_type', 12)->default('PROTOTYPE');
            $table->date('date');
            $table->string('status', 10)->default('DRAFT');       // DRAFT | FINAL
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('npd_cp_det', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();       // npd_cp_main
            $table->unsignedSmallInteger('seq')->default(1);
            $table->unsignedBigInteger('proc_id')->nullable();    // m_process
            $table->unsignedBigInteger('param_id')->nullable();   // m_inspection_param
            $table->decimal('nominal', 12, 4)->nullable();
            $table->decimal('min_value', 12, 4)->nullable();
            $table->decimal('max_value', 12, 4)->nullable();
            $table->string('method', 100)->nullable();            // alat & cara ukur
            $table->unsignedSmallInteger('sample_size')->default(1);
            $table->string('frequency', 60)->nullable();          // mis. tiap 50 pcs
            $table->string('control_method', 150)->nullable();
            $table->string('reaction_plan', 250)->nullable();
            // Baris asalnya dari FMEA yang mana — jejak "kenapa ini diukur".
            $table->unsignedBigInteger('fmea_det_id')->nullable();
            /*
             * Ikut dibuat menjadi parameter inspeksi item saat serah terima.
             * Tidak semua baris control plan pantas jadi parameter QC produksi —
             * sebagian mengendalikan setelan mesin, bukan ciri barangnya.
             */
            $table->boolean('to_item_inspection')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['npd_cp_det', 'npd_cp_main', 'npd_fmea_det', 'npd_fmea_main'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
