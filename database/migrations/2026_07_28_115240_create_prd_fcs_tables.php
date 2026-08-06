<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prd_fcs_main', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wo_id');
            $table->unsignedBigInteger('fg_item_id');
            $table->integer('qty_planned')->default(0);
            $table->integer('qty_good')->default(0);
            $table->string('status', 20)->default('PENDING');
            $table->json('traceability')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prd_fcs_main');
    }
};
