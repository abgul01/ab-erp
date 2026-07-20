<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a Work Order back to the MPS entry it was created from (planning chain
 * MPP → MPS → WO). Nullable so legacy/manual WOs still work.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prd_wo_main', function (Blueprint $table) {
            if (! Schema::hasColumn('prd_wo_main', 'mps_id')) {
                $table->integer('mps_id')->nullable()->after('fg_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prd_wo_main', function (Blueprint $table) {
            if (Schema::hasColumn('prd_wo_main', 'mps_id')) {
                $table->dropColumn('mps_id');
            }
        });
    }
};
