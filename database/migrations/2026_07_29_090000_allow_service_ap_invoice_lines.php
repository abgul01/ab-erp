<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An AP invoice line does not always come from a goods receipt.
 *
 * Subcontracting, freight and other services are invoiced without anything
 * arriving at the dock, so there is no prc_gr_detail row to point at. The
 * column was NOT NULL, which made those invoices impossible to record — and
 * they are exactly the ones PPh 23 is withheld on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prc_inv_detail', function (Blueprint $table) {
            $table->unsignedBigInteger('gr_detail_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Service lines would block this, so clear them first.
        DB::table('prc_inv_detail')->whereNull('gr_detail_id')->delete();

        Schema::table('prc_inv_detail', function (Blueprint $table) {
            $table->unsignedBigInteger('gr_detail_id')->nullable(false)->change();
        });
    }
};
