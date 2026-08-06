<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // prd_crp already exists from initial DB setup — nothing to do.
    }

    public function down(): void
    {
        Schema::dropIfExists('prd_crp');
    }
};
