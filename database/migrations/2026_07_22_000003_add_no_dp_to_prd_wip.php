<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A WIP is created the first time production scans the Denpyou (no_dp) for a
 * released Work Order — it is never picked from a list. Carrying no_dp on
 * prd_wip makes that scan the single entry point for the shop-floor screens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prd_wip', function (Blueprint $t) {
            $t->string('no_dp', 40)->nullable()->after('code');
            $t->string('user_id', 10)->nullable()->after('item_id');
            $t->date('date')->nullable()->after('user_id');
            $t->index('no_dp');
        });

        // the cutting header also records the shift, like the reference screen
        Schema::table('tr_cut_main', function (Blueprint $t) {
            $t->unsignedInteger('shift_id')->nullable()->after('date');
        });
    }

    public function down(): void
    {
        Schema::table('prd_wip', function (Blueprint $t) {
            $t->dropIndex(['no_dp']);
            $t->dropColumn(['no_dp', 'user_id', 'date']);
        });
        Schema::table('tr_cut_main', function (Blueprint $t) {
            $t->dropColumn('shift_id');
        });
    }
};
