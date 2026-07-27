<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MPS becomes operation-level: each row is now one routing operation (a lot of
 * an item at a specific process on a specific machine on a date), so the plan
 * flows through the item's routing. proc_id nullable = the process this lot
 * covers (null for legacy/manual rows without routing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prd_mps', function (Blueprint $t) {
            $t->unsignedInteger('proc_id')->nullable()->after('item_id');
            $t->index(['item_id', 'proc_id']);
        });
    }

    public function down(): void
    {
        Schema::table('prd_mps', function (Blueprint $t) {
            $t->dropIndex(['item_id', 'proc_id']);
            $t->dropColumn('proc_id');
        });
    }
};
