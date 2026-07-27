<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abnormality is now recorded first and judged later: the finding carries the
 * quantity, and a separate decision turns it into NG (tr_ng_*) or repair
 * (tr_repair_*). tr_ab_cut_det already has qty; tr_ab_pro was missing it
 * (the reference app's model does list it), so add it here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tr_ab_pro', function (Blueprint $t) {
            $t->integer('qty')->default(0)->after('pallet_code');
        });
    }

    public function down(): void
    {
        Schema::table('tr_ab_pro', function (Blueprint $t) {
            $t->dropColumn('qty');
        });
    }
};
