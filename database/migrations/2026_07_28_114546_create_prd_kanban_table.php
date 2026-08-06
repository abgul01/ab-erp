<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prd_kanban', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->unsignedBigInteger('wo_id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('rm_detail_id')->nullable();
            $table->integer('qty_planned')->default(0);
            $table->integer('qty_issued')->default(0);
            $table->string('status', 20)->default('DRAFT'); // DRAFT|OPEN|ISSUED|RETURNED|CLOSED
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prd_kanban');
    }
};
