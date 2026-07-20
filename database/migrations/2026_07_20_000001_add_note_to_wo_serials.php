<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-serial notes on WO booking rows (matches the WO create workspace).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prd_wo_serial_rm', function (Blueprint $table) {
            if (! Schema::hasColumn('prd_wo_serial_rm', 'note')) {
                $table->string('note', 150)->nullable()->after('scrap');
            }
        });
        Schema::table('prd_wo_serial_pm', function (Blueprint $table) {
            if (! Schema::hasColumn('prd_wo_serial_pm', 'note')) {
                $table->string('note', 150)->nullable()->after('qty');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prd_wo_serial_rm', function (Blueprint $table) {
            if (Schema::hasColumn('prd_wo_serial_rm', 'note')) {
                $table->dropColumn('note');
            }
        });
        Schema::table('prd_wo_serial_pm', function (Blueprint $table) {
            if (Schema::hasColumn('prd_wo_serial_pm', 'note')) {
                $table->dropColumn('note');
            }
        });
    }
};
