<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PPh 22 on imports is computed on the cost sheet but is NOT part of landed
 * cost — it is a prepaid tax credit, so it lives on the header and is left out
 * of the per-kilo allocation. Keeping it here rather than as a cost line is
 * what stops it leaking into inventory value.
 *
 * PRD §6
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prc_cost_main', function (Blueprint $table) {
            $table->decimal('pph22_base', 18, 2)->default(0)->after('alloc_basis');
            $table->decimal('pph22_rate', 6, 2)->default(0)->after('pph22_base');
            $table->decimal('pph22_amount', 18, 2)->default(0)->after('pph22_rate');
            $table->boolean('has_api')->default(true)->after('pph22_amount');
        });
    }

    public function down(): void
    {
        Schema::table('prc_cost_main', fn (Blueprint $t) => $t->dropColumn(
            ['pph22_base', 'pph22_rate', 'pph22_amount', 'has_api']
        ));
    }
};
