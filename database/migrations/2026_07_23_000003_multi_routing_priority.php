<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One item may now follow several routing templates, ranked by priority — the
 * WO picks which one to run. m_bom_pro gains `priority` (multiple rows per
 * item), and the WO records the chosen routing in prd_wo_main.process_main_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_bom_pro', function (Blueprint $t) {
            $t->tinyInteger('priority')->default(1)->after('process_main_id');
        });
        Schema::table('prd_wo_main', function (Blueprint $t) {
            $t->unsignedInteger('process_main_id')->nullable()->after('fg_id');
        });
    }

    public function down(): void
    {
        Schema::table('m_bom_pro', function (Blueprint $t) {
            $t->dropColumn('priority');
        });
        Schema::table('prd_wo_main', function (Blueprint $t) {
            $t->dropColumn('process_main_id');
        });
    }
};
