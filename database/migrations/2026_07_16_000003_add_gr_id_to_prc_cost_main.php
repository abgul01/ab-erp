<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Landed cost is incurred per arrival (GRN / Bill of Lading), not per PO.
 * Scope the cost sheet to a goods receipt; po_id stays (derived from the GR)
 * for continuity with the legacy NOT NULL column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prc_cost_main', function (Blueprint $table) {
            if (! Schema::hasColumn('prc_cost_main', 'gr_id')) {
                $table->integer('gr_id')->nullable()->after('po_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prc_cost_main', function (Blueprint $table) {
            if (Schema::hasColumn('prc_cost_main', 'gr_id')) {
                $table->dropColumn('gr_id');
            }
        });
    }
};
