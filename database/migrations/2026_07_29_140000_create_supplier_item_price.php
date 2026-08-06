<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supplier Item & Price (PRD §4.7) — one material, several suppliers.
 *
 * MOQ, order lot and lead time were put on the item master when MRP first
 * needed them, which was a simplification: those numbers belong to a supplier,
 * not to the steel. One mill sells in bundles of 25 on 45-day terms while
 * another sells singles in a fortnight, and MRP cannot say "buy this" until it
 * knows which of them it is planning against.
 *
 * The item-level columns stay as the fallback for materials with no supplier
 * agreed yet, so nothing breaks while this master is being filled in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_supplier_item', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ven_id')->index();
            $table->unsignedBigInteger('item_id')->index();

            // Lower number wins when MRP picks who to buy from.
            $table->unsignedSmallInteger('priority')->default(1);

            $table->decimal('price', 18, 4)->default(0);
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->unsignedInteger('moq')->default(0);
            $table->unsignedInteger('order_lot')->default(0);
            $table->unsignedSmallInteger('lead_time_days')->default(0);

            $table->string('supplier_part_no', 50)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            // One agreement per supplier per material.
            $table->unique(['ven_id', 'item_id']);
        });

        Schema::table('prc_pr_detail', function (Blueprint $table) {
            // Which supplier MRP suggests, and what it expects to pay. A
            // requisition that names neither leaves the buyer to rediscover it.
            $table->unsignedBigInteger('ven_id')->nullable()->after('item_id');
            $table->decimal('est_price', 18, 4)->default(0)->after('ven_id');
        });
    }

    public function down(): void
    {
        Schema::table('prc_pr_detail', fn (Blueprint $t) => $t->dropColumn(['ven_id', 'est_price']));
        Schema::dropIfExists('m_supplier_item');
    }
};
