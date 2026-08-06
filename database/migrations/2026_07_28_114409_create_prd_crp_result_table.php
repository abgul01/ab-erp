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
        Schema::create('prd_crp_result', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('crp_id');
            $table->unsignedBigInteger('process_id');
            $table->unsignedBigInteger('machine_id')->nullable();
            $table->decimal('load_hours', 10, 2)->default(0);
            $table->decimal('available_hours', 10, 2)->default(0);
            $table->decimal('load_pct', 6, 1)->default(0);
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prd_crp_result');
    }
};
