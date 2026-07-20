<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PO arrival estimate lives on the header (ETA), not per line; RM steel is
 * priced per kg alongside the per-pcs price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prc_po_main', function (Blueprint $table) {
            if (! Schema::hasColumn('prc_po_main', 'eta')) {
                $table->date('eta')->nullable()->after('top_days');
            }
        });
        Schema::table('prc_po_detail', function (Blueprint $table) {
            if (! Schema::hasColumn('prc_po_detail', 'price_kg')) {
                $table->decimal('price_kg', 18, 4)->nullable()->after('price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prc_po_main', function (Blueprint $table) {
            if (Schema::hasColumn('prc_po_main', 'eta')) {
                $table->dropColumn('eta');
            }
        });
        Schema::table('prc_po_detail', function (Blueprint $table) {
            if (Schema::hasColumn('prc_po_detail', 'price_kg')) {
                $table->dropColumn('price_kg');
            }
        });
    }
};
