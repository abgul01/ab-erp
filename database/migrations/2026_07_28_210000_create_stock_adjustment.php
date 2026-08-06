<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock opname and adjustment (PRD §4.8 Transaction).
 *
 * An adjustment records both the counted figure and the system figure at the
 * moment of counting, not just the difference — a variance nobody can trace
 * back to what the system thought is impossible to audit later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wh_adj_main', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->date('date');
            $table->string('adj_type', 20);              // OPNAME | IN | OUT | BEGIN
            $table->string('warehouse', 20)->default('RM'); // RM | FG | GENERAL
            $table->string('reason', 300)->nullable();
            $table->string('status', 20)->default('DRAFT'); // DRAFT | POSTED | CANCELLED
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wh_adj_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();
            $table->unsignedBigInteger('item_id')->index();
            $table->string('serial_id', 50)->nullable();
            $table->decimal('qty_system', 14, 2)->default(0);
            $table->decimal('qty_counted', 14, 2)->default(0);
            $table->decimal('qty_diff', 14, 2)->default(0);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->string('note', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wh_adj_detail');
        Schema::dropIfExists('wh_adj_main');
    }
};
