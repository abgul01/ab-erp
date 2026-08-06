<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Packing List and Shipping Order (PRD §5.7 dokumen pengiriman).
 *
 * The Delivery Order says what was sold and shipped; it does not say how the
 * goods were packed or what left the gate on which truck. Those are the two
 * documents a driver, a customer's receiving bay and a freight forwarder all
 * actually work from:
 *
 *   Packing List  — how one delivery is divided into boxes, and what each box
 *                   weighs. A receiving bay checks boxes, not order lines.
 *   Shipping Order— one vehicle, one trip, possibly several deliveries. It is
 *                   what the gate logs and what carries the driver's details.
 *
 * Both sit beside the Delivery Order rather than inside it, because one
 * delivery can be packed into many boxes and one truck can carry many
 * deliveries; folding either into sls_do_detail would lose that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sls_pack_main', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->date('date');
            $table->unsignedBigInteger('do_id')->index();
            $table->string('status', 20)->default('DRAFT');  // DRAFT | FINAL
            $table->string('note', 300)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('sls_pack_det', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();
            // Box number is the customer's reference when something is missing.
            $table->string('box_no', 30);
            $table->unsignedBigInteger('do_detail_id')->index();
            $table->unsignedBigInteger('item_id')->index();
            $table->integer('qty');
            $table->decimal('net_weight', 12, 2)->default(0);
            $table->decimal('gross_weight', 12, 2)->default(0);
            $table->string('dimension', 40)->nullable();     // P×L×T mm
            $table->string('note', 150)->nullable();
        });

        Schema::create('sls_ship_main', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->date('date');
            $table->unsignedBigInteger('carrier_id')->nullable(); // ekspedisi (m_contacts)
            $table->string('vehicle_no', 20)->nullable();
            $table->string('driver', 60)->nullable();
            $table->string('driver_phone', 25)->nullable();
            $table->string('destination', 200)->nullable();
            $table->dateTime('plan_depart')->nullable();
            $table->dateTime('departed_at')->nullable();
            $table->dateTime('arrived_at')->nullable();
            $table->string('status', 20)->default('DRAFT');  // DRAFT|DISPATCHED|DELIVERED|CANCELLED
            $table->string('note', 300)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('sls_ship_det', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();
            $table->unsignedBigInteger('do_id')->index();
            // A delivery rides on one truck; two shipping orders claiming the
            // same delivery would mean nobody knows where the goods are.
            $table->unique('do_id', 'uq_ship_do');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sls_ship_det');
        Schema::dropIfExists('sls_ship_main');
        Schema::dropIfExists('sls_pack_det');
        Schema::dropIfExists('sls_pack_main');
    }
};
