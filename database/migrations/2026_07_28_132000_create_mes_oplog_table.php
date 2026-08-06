<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mes_oplog', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->string('type', 50);
            $table->string('table_name', 50)->nullable();
            $table->unsignedBigInteger('row_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('OK');
            $table->string('error', 200)->nullable();
            $table->timestamps();
        });

        Schema::table('tr_cut_main', function (Blueprint $table) {
            $table->unique('client_uuid', 'tr_cut_main_client_uuid_unique');
        });

        Schema::table('tr_pro_main', function (Blueprint $table) {
            $table->unique('client_uuid', 'tr_pro_main_client_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tr_pro_main', function (Blueprint $table) {
            $table->dropIndex('tr_pro_main_client_uuid_unique');
        });
        Schema::table('tr_cut_main', function (Blueprint $table) {
            $table->dropIndex('tr_cut_main_client_uuid_unique');
        });
        Schema::dropIfExists('mes_oplog');
    }
};
