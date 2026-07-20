<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core table for race-safe monthly document numbering (LLD 3.5 / 4.1).
 * Additive only — does not alter any existing ab-erp table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('doc_numberings')) {
            return;
        }

        Schema::create('doc_numberings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('doc_type', 30);
            $table->string('prefix', 10);
            $table->char('period', 6); // YYYYMM
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['doc_type', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_numberings');
    }
};
