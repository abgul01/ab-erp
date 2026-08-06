<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchasing constraints on the item master.
 *
 * MRP has to turn a net shortage into a quantity a supplier will actually
 * accept. That needs two numbers the schema did not carry: the minimum the mill
 * will sell, and the bundle size it ships in.
 *
 * They are added as their own columns rather than borrowed from min_stock /
 * max_stock — those are the reorder point and the stock ceiling, and reading
 * them as order multiples turned a shortage of 9 bars into an order for 500.
 *
 * Zero means "no constraint", which is the right default for one-off buys.
 *
 * PRD §4.7 (Supplier Item & Price: MOQ, lead time)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_item', function (Blueprint $table) {
            $table->unsignedInteger('moq')->default(0)->after('max_stock');
            $table->unsignedInteger('order_lot')->default(0)->after('moq');
            $table->unsignedSmallInteger('lead_time_days')->default(0)->after('order_lot');
        });
    }

    public function down(): void
    {
        Schema::table('m_item', fn (Blueprint $t) => $t->dropColumn(['moq', 'order_lot', 'lead_time_days']));
    }
};
