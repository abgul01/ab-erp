<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sidebar used to fall back on the row id for ordering, so any menu added
 * later was stuck at the bottom regardless of where it belongs. An explicit
 * sort lets the seeder decide the order (its array order is the menu order).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $t) {
            $t->unsignedSmallInteger('sort')->default(0)->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $t) {
            $t->dropColumn('sort');
        });
    }
};
