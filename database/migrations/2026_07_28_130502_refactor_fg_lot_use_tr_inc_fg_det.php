<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add costing & source tracking to existing FG lots table
        Schema::table('tr_inc_fg_det', function (Blueprint $table) {
            $table->decimal('unit_cost', 14, 4)->default(0)->after('qty');
            $table->string('source', 20)->default('FG_IN')->after('unit_cost');
            $table->unsignedBigInteger('parent_lot_id')->nullable()->after('source');
            $table->string('status', 20)->default('ACTIVE')->after('parent_lot_id');
        });

        // Drop the redundant table we created
        Schema::dropIfExists('wh_fg_lot');
    }

    public function down(): void
    {
        Schema::table('tr_inc_fg_det', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'source', 'parent_lot_id', 'status']);
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
};
