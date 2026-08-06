<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scrap decisions are an audit record, not a status field: PRD §5 requires the
 * reason for every scrap ⇄ usable call to survive, including the ones that
 * overrode the automatic flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prd_scrap_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('serial_id', 50)->index();
            $table->unsignedBigInteger('wo_serial_rm_id')->index();
            $table->unsignedBigInteger('wo_id')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->decimal('length_rem', 12, 2)->default(0);
            $table->decimal('min_bom_length', 12, 2)->nullable();
            $table->string('decision', 10);              // SCRAP | USABLE
            $table->string('reason', 300);
            $table->boolean('auto_flag')->default(false); // what the rule said
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prd_scrap_decisions');
    }
};
