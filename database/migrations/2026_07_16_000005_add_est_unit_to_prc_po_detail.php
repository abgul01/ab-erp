<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-unit (per bar) estimates on a PO line. The existing est_weight / est_length
 * become the totals, derived as qty × the per-unit value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prc_po_detail', function (Blueprint $table) {
            if (! Schema::hasColumn('prc_po_detail', 'est_weight_unit')) {
                $table->double('est_weight_unit', 10, 2)->nullable()->after('price');
            }
            if (! Schema::hasColumn('prc_po_detail', 'est_length_unit')) {
                $table->double('est_length_unit', 10, 2)->nullable()->after('est_weight_unit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prc_po_detail', function (Blueprint $table) {
            foreach (['est_weight_unit', 'est_length_unit'] as $col) {
                if (Schema::hasColumn('prc_po_detail', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
