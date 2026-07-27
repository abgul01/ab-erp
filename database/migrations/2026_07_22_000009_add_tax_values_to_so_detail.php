<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freeze the tax arithmetic on the SO line. Rates in m_tax change over time, so
 * the document keeps the DPP (tax base) and the PPN / PPh amounts it was priced
 * with, plus which PPh tariff was applied (tax_id already holds the PPN one).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sls_so_detail', function (Blueprint $t) {
            $t->unsignedInteger('pph_tax_id')->nullable()->after('tax_id');
            $t->decimal('dpp', 18, 2)->default(0)->after('pph');
            $t->decimal('ppn_value', 18, 2)->default(0)->after('dpp');
            $t->decimal('pph_value', 18, 2)->default(0)->after('ppn_value');
        });
    }

    public function down(): void
    {
        Schema::table('sls_so_detail', function (Blueprint $t) {
            $t->dropColumn(['pph_tax_id', 'dpp', 'ppn_value', 'pph_value']);
        });
    }
};
