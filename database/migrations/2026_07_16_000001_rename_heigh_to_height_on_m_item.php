<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy schema misspelled the item height column as `heigh`.
 * The m_item model uses `height`; align the DB column to match.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('m_item', 'heigh') && ! Schema::hasColumn('m_item', 'height')) {
            Schema::table('m_item', function (Blueprint $table) {
                $table->renameColumn('heigh', 'height');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('m_item', 'height') && ! Schema::hasColumn('m_item', 'heigh')) {
            Schema::table('m_item', function (Blueprint $table) {
                $table->renameColumn('height', 'heigh');
            });
        }
    }
};
