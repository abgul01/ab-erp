<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * m_item_customer only needs item_id + cus_id + priority + active.
 * Drop the unused cust part / qty / default columns and add `priority`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_item_customer', function (Blueprint $table) {
            if (! Schema::hasColumn('m_item_customer', 'priority')) {
                $table->integer('priority')->default(0)->after('cus_id');
            }
            foreach (['cus_part_no', 'cus_part_name', 'qty_per_box', 'is_default'] as $col) {
                if (Schema::hasColumn('m_item_customer', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('m_item_customer', function (Blueprint $table) {
            if (Schema::hasColumn('m_item_customer', 'priority')) {
                $table->dropColumn('priority');
            }
            if (! Schema::hasColumn('m_item_customer', 'cus_part_no')) {
                $table->string('cus_part_no', 50)->nullable()->after('cus_id');
                $table->string('cus_part_name', 100)->nullable()->after('cus_part_no');
                $table->integer('qty_per_box')->nullable()->after('cus_part_name');
                $table->tinyInteger('is_default')->default(0)->after('qty_per_box');
            }
        });
    }
};
