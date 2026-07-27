<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * m_bom_pro becomes the "Master Item Process" link: one row per item pointing at
 * the routing template it uses. The old per-item steps (m_bom_pro_det) are no
 * longer written — the routing now lives on the template.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_bom_pro', function (Blueprint $t) {
            $t->unsignedInteger('process_main_id')->nullable()->after('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('m_bom_pro', function (Blueprint $t) {
            $t->dropColumn('process_main_id');
        });
    }
};
