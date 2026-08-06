<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRD §4.2–4.7 puts master data behind approval too, but these tables only ever
 * carried an `active` flag. Existing rows are stamped APPROVED so nothing that
 * works today starts failing — approval applies from here forward.
 */
return new class extends Migration
{
    private const TABLES = ['m_item', 'm_bom', 'm_process_main', 'sls_forecast', 'm_quota', 'm_contacts'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'status')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->string('status', 20)->default('APPROVED')->index();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'status')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('status'));
            }
        }
    }
};
