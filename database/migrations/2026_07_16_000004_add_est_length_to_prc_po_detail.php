<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PO line estimated length (mm) — companion to est_weight for pipe/RM planning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prc_po_detail', function (Blueprint $table) {
            if (! Schema::hasColumn('prc_po_detail', 'est_length')) {
                $table->double('est_length', 10, 2)->nullable()->after('est_weight');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prc_po_detail', function (Blueprint $table) {
            if (Schema::hasColumn('prc_po_detail', 'est_length')) {
                $table->dropColumn('est_length');
            }
        });
    }
};
