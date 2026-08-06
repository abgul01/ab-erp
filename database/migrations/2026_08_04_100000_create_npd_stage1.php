<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New Product Development — Tahap 1 (PRD_Modul_NPD_FTPI.md §12).
 *
 * Proyek pengembangan part baru mengikuti 5 fase APQP, dengan gate approval di
 * setiap peralihan fase. Yang dibangun di tahap ini adalah kerangkanya: proyek,
 * RFQ, feasibility, fase, task, milestone, deliverable, dokumen, dan tim. BOM,
 * costing, trial, FMEA, control plan, PPAP, dan handover menyusul di tahap
 * berikutnya — tabelnya sengaja belum dibuat supaya tidak ada skema kosong yang
 * menganggur dan menyesatkan pembacanya.
 *
 * Dua hal yang tidak dibuat di sini karena mesinnya sudah ada:
 *
 *   Gate approval memakai `approvals` + ApprovalEngine, dengan doc_type
 *   `npd_project_phase`. Dua level untuk semua gate: pengaju (PM/Engineering)
 *   lalu SPV.
 *
 *   Permintaan perubahan teknik memakai modul ECN yang sudah ada; di sini hanya
 *   ditambahkan tautan balik ke proyeknya.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Master: 5 fase APQP ----
        Schema::create('npd_phase', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('phase_no')->unique();   // 1..5
            $table->string('name', 60);
            $table->string('descrip', 200)->nullable();
            $table->timestamps();
        });

        // ---- Master: deliverable wajib per fase ----
        Schema::create('npd_deliverable_std', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('phase_id')->index();
            $table->string('code', 20);
            $table->string('name', 100);
            /*
             * Gate ditolak selama deliverable wajib fase itu belum selesai.
             * Daftarnya master, bukan diketik ulang tiap proyek — kalau tidak,
             * "checklist lengkap" hanya berarti seseorang lupa menambah baris.
             */
            $table->boolean('mandatory')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['phase_id', 'code']);
        });

        // ---- Proyek ----
        Schema::create('npd_project', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->unsignedBigInteger('cus_id')->index();            // m_contacts
            // Part induk untuk proyek modifikasi/derivatif.
            $table->unsignedBigInteger('parent_item_id')->nullable();
            $table->string('part_name', 100);
            $table->string('drawing_no', 50)->nullable();
            $table->string('project_type', 15)->default('NEW');       // NEW|MODIFICATION|DERIVATIVE
            $table->unsignedTinyInteger('current_phase_no')->default(1);
            // DRAFT|RUNNING|ON_HOLD|HANDOVER|CLOSED|CANCELLED
            $table->string('status', 15)->default('DRAFT');
            $table->date('target_sop')->nullable();
            $table->unsignedBigInteger('pm_user_id')->nullable();
            $table->string('priority', 10)->default('NORMAL');        // LOW|NORMAL|HIGH
            /*
             * Part didaftarkan sebagai m_item non-aktif sejak fase desain, bukan
             * saat handover: sebuah Work Order trial membutuhkan fg_id, dan
             * nomor part sudah diketahui begitu drawing pelanggan diterima.
             */
            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedBigInteger('bom_id')->nullable();
            $table->unsignedBigInteger('process_main_id')->nullable();
            $table->date('handover_date')->nullable();
            $table->string('note', 300)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'current_phase_no']);
        });

        // ---- RFQ / inquiry ----
        Schema::create('npd_rfq', function (Blueprint $table) {
            $table->id();
            // Boleh belum punya proyek: RFQ masuk lebih dulu, proyek lahir
            // setelah feasibility-nya Go.
            $table->unsignedBigInteger('main_id')->nullable()->index();
            $table->unsignedBigInteger('cus_id')->index();
            $table->string('code', 50);                 // nomor RFQ pelanggan
            $table->date('date');
            $table->string('part_name', 100);
            $table->string('drawing_ref', 50)->nullable();
            $table->integer('qty')->default(0);
            $table->decimal('target_price', 18, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('status', 15)->default('OPEN');   // OPEN|QUOTED|WON|LOST
            $table->string('note', 300)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        // ---- Feasibility study ----
        Schema::create('npd_feasibility', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rfq_id')->index();
            $table->unsignedBigInteger('main_id')->nullable()->index();  // npd_project bila Go
            $table->boolean('tech_ok')->default(false);
            $table->boolean('capacity_ok')->default(false);
            $table->boolean('cost_ok')->default(false);
            $table->string('material_avail', 150)->nullable();
            $table->string('conclusion', 12)->default('CONDITIONAL');    // GO|NO_GO|CONDITIONAL
            $table->string('note', 400)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();
        });

        // ---- Proyek per fase: inilah dokumen yang di-gate ----
        Schema::create('npd_project_phase', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();     // npd_project
            $table->unsignedBigInteger('phase_id')->index();
            $table->unsignedTinyInteger('phase_no');
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->date('actual_start')->nullable();
            $table->date('actual_end')->nullable();
            // PLANNED|RUNNING|SUBMITTED|APPROVED|REJECTED
            $table->string('status', 15)->default('PLANNED');
            $table->unsignedBigInteger('pic_user_id')->nullable();
            $table->timestamps();

            $table->unique(['main_id', 'phase_no']);
        });

        // ---- Task ----
        Schema::create('npd_task', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();     // npd_project_phase
            $table->string('name', 150);
            $table->string('descrip', 300)->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->date('actual_start')->nullable();
            $table->date('actual_end')->nullable();
            $table->unsignedTinyInteger('progress_pct')->default(0);
            // Task yang harus selesai lebih dulu.
            $table->unsignedBigInteger('predecessor_id')->nullable();
            $table->string('status', 12)->default('OPEN');      // OPEN|RUNNING|DONE|CANCELLED
            $table->timestamps();
        });

        // ---- Milestone ----
        Schema::create('npd_milestone', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();     // npd_project
            $table->string('name', 100);
            $table->date('planned_date');
            $table->date('actual_date')->nullable();
            $table->string('status', 10)->default('PLANNED');   // PLANNED|DONE|LATE
            $table->timestamps();
        });

        // ---- Deliverable per fase ----
        Schema::create('npd_deliverable', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();     // npd_project_phase
            $table->unsignedBigInteger('std_id')->nullable();   // npd_deliverable_std
            $table->string('title', 150);
            // OPEN|IN_PROGRESS|DONE|WAIVED — WAIVED butuh alasan tertulis.
            $table->string('status', 12)->default('OPEN');
            $table->unsignedBigInteger('resp_user_id')->nullable();
            $table->date('due_date')->nullable();
            $table->date('submitted_date')->nullable();
            $table->unsignedBigInteger('doc_id')->nullable();
            $table->string('note', 300)->nullable();
            $table->timestamps();
        });

        // ---- Dokumen berversi ----
        Schema::create('npd_doc', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();     // npd_project
            // Menempel polimorfik: DELIVERABLE, TRIAL, FMEA, PPAP, dst.
            $table->string('ref_type', 20)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('doc_type', 20)->default('OTHER');   // DRAWING|SPEC|REPORT|CERT|OTHER
            $table->string('file_name', 200);
            $table->string('file_path', 300);
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size_kb')->default(0);
            $table->string('version', 20)->default('rev A');
            // Revisi lama tetap tersimpan; hanya satu yang berlaku.
            $table->boolean('is_current')->default(true);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['ref_type', 'ref_id']);
        });

        // ---- Anggota tim ----
        Schema::create('npd_member', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();     // npd_project
            $table->unsignedBigInteger('user_id')->index();
            // PM|DESIGN|PROCESS|QUALITY|PROCUREMENT|COSTING|VIEWER
            $table->string('role', 15)->default('VIEWER');
            $table->timestamps();

            $table->unique(['main_id', 'user_id']);
        });

        /*
         * Perubahan teknik yang lahir selama proyek tetap memakai modul ECN —
         * lengkap dengan daftar kolom yang boleh diubah, cek data berubah sejak
         * disetujui, tanggal efektif, dan kenaikan revisi. Yang kurang hanya
         * tautan balik ke proyeknya.
         */
        Schema::table('eng_ecn_main', function (Blueprint $table) {
            $table->unsignedBigInteger('npd_project_id')->nullable()->after('item_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('eng_ecn_main', fn (Blueprint $t) => $t->dropColumn('npd_project_id'));

        foreach ([
            'npd_member', 'npd_doc', 'npd_deliverable', 'npd_milestone', 'npd_task',
            'npd_project_phase', 'npd_feasibility', 'npd_rfq', 'npd_project',
            'npd_deliverable_std', 'npd_phase',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
