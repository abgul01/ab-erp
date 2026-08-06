<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_fg_downgrade_map', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fg_item_id');
            $table->unsignedBigInteger('material_item_id');
            $table->string('note', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('wh_fg_lot', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->string('lot_code', 100)->nullable();
            $table->integer('qty')->default(0);
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->string('source', 20)->default('FG_IN');
            $table->string('status', 20)->default('ACTIVE');
            $table->unsignedBigInteger('parent_lot_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wh_fg_lot');
        Schema::dropIfExists('m_fg_downgrade_map');
    }
};
