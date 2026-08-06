<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optimistic locking on the booked serial (LLD §5.4).
 *
 * Two terminals can scan the same bar within the same second — cutting and a
 * remnant return, say. Without a version check the second write silently
 * overwrites the first and the remaining length is wrong on the rack.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prd_wo_serial_rm', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(0)->after('scrap');
        });
    }

    public function down(): void
    {
        Schema::table('prd_wo_serial_rm', fn (Blueprint $t) => $t->dropColumn('version'));
    }
};
