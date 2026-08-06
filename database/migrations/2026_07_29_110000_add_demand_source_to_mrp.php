<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which signal drove the requirement.
 *
 * Gross demand is the highest of the approved plan, the forecast and the firm
 * sales orders. Without recording which one won, a planner cannot tell whether
 * a number came from a customer order or from someone's estimate — and those
 * carry very different confidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prd_mrp_detail', function (Blueprint $table) {
            $table->string('demand_src', 12)->nullable()->after('gross_req');
            $table->integer('demand_mpp')->default(0)->after('demand_src');
            $table->integer('demand_fc')->default(0)->after('demand_mpp');
            $table->integer('demand_so')->default(0)->after('demand_fc');
        });
    }

    public function down(): void
    {
        Schema::table('prd_mrp_detail', fn (Blueprint $t) => $t->dropColumn(
            ['demand_src', 'demand_mpp', 'demand_fc', 'demand_so']
        ));
    }
};
