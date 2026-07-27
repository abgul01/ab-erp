<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One customer PO often carries its own line codes, so each SO line can record
 * the PO detail code it answers to (the header keeps the PO number itself).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sls_so_detail', function (Blueprint $t) {
            $t->string('po_detail_code', 50)->nullable()->after('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('sls_so_detail', function (Blueprint $t) {
            $t->dropColumn('po_detail_code');
        });
    }
};
