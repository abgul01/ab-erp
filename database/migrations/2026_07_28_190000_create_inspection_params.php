<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master Inspection (PRD §4.6).
 *
 * `qc_incoming_det` already existed to hold a measured value per parameter, but
 * there was nowhere to define what the parameters are or what counts as within
 * spec — so QAS could only record a verdict, never the readings behind it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_inspection_param', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('uom', 20)->nullable();
            $table->string('method', 100)->nullable();   // how it is measured
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Which parameters apply to an item, and the tolerance for each.
        Schema::create('m_item_inspection', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id')->index();
            $table->unsignedBigInteger('param_id')->index();
            $table->decimal('nominal', 14, 4)->nullable();
            $table->decimal('min_value', 14, 4)->nullable();
            $table->decimal('max_value', 14, 4)->nullable();
            $table->boolean('mandatory')->default(true);
            $table->timestamps();

            $table->unique(['item_id', 'param_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_item_inspection');
        Schema::dropIfExists('m_inspection_param');
    }
};
