<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pieces judged "repair" must be run again at the process that rejected them.
 * A transaction flagged repair = 1 is that rework run (tr_cut_main already has
 * the flag; tr_pro_main was missing it even though the reference screen has the
 * Repair checkbox).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tr_pro_main', function (Blueprint $t) {
            $t->tinyInteger('repair')->default(0)->after('subcont');
        });
    }

    public function down(): void
    {
        Schema::table('tr_pro_main', function (Blueprint $t) {
            $t->dropColumn('repair');
        });
    }
};
