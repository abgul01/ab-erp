<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Align tr_pro_* with the reference application's models: the Processing
 * transaction also carries a finish flag, the "Continue Process" toggle
 * (cont_pro = finishing the second side), the routing sequence it covers
 * (sq_process), subcontract flag and shift. Machine rows carry their operator
 * and their own finish flag. Purely additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tr_pro_main', function (Blueprint $t) {
            $t->tinyInteger('finish')->default(0)->after('end_time');
            $t->tinyInteger('cont_pro')->default(0)->after('finish');
            $t->unsignedTinyInteger('sq_process')->nullable()->after('cont_pro');
            $t->tinyInteger('subcont')->default(0)->after('sq_process');
            $t->unsignedInteger('shift_id')->nullable()->after('subcont');
        });

        Schema::table('tr_pro_detail', function (Blueprint $t) {
            $t->string('user_id', 50)->nullable()->after('machine_id');
            $t->tinyInteger('finish')->default(0)->after('qty_full');
            $t->time('start_time')->nullable()->after('finish');
            $t->time('end_time')->nullable()->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('tr_pro_main', function (Blueprint $t) {
            $t->dropColumn(['finish', 'cont_pro', 'sq_process', 'subcont', 'shift_id']);
        });
        Schema::table('tr_pro_detail', function (Blueprint $t) {
            $t->dropColumn(['user_id', 'finish', 'start_time', 'end_time']);
        });
    }
};
