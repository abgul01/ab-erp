<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restructure (per user 2026-07-17):
 *  - 1 GR dapat memuat lebih dari 1 PO → per-line prc_gr_detail.po_id; po_no header dilebarkan.
 *  - Landed cost mengacu 1 AP invoice → prc_cost_main.inv_id.
 *  - Outgoing RM belum wajib WO/customer → wh_out_main.wo_id & cus_id nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prc_gr_detail', function ($table) {
            if (! Schema::hasColumn('prc_gr_detail', 'po_id')) {
                $table->integer('po_id')->nullable()->after('id_prim');
            }
        });
        DB::statement('ALTER TABLE prc_gr_main MODIFY po_no VARCHAR(255) NOT NULL');

        Schema::table('prc_cost_main', function ($table) {
            if (! Schema::hasColumn('prc_cost_main', 'inv_id')) {
                $table->integer('inv_id')->nullable()->after('gr_id');
            }
        });

        DB::statement('ALTER TABLE wh_out_main MODIFY wo_id INT NULL');
        DB::statement('ALTER TABLE wh_out_main MODIFY cus_id INT NULL');

        // Backfill: existing GR details point to the PO named on their header.
        DB::statement('
            UPDATE prc_gr_detail d
            JOIN prc_gr_main m ON m.id = d.id_prim
            JOIN prc_po_main p ON p.code = m.po_no
            SET d.po_id = p.id
            WHERE d.po_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('prc_gr_detail', function ($table) {
            if (Schema::hasColumn('prc_gr_detail', 'po_id')) {
                $table->dropColumn('po_id');
            }
        });
        Schema::table('prc_cost_main', function ($table) {
            if (Schema::hasColumn('prc_cost_main', 'inv_id')) {
                $table->dropColumn('inv_id');
            }
        });
        DB::statement('ALTER TABLE prc_gr_main MODIFY po_no VARCHAR(50) NOT NULL');
    }
};
