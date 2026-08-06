<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('prd_crp_result');
    }

    public function down(): void
    {
        // Not recreating — prd_crp table already covers this concept.
    }
};
