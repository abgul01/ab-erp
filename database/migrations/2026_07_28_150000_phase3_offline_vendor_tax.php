<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 — MES offline replay, vendor portal, tax export.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The offline queue replays the original API call, so the log keeps it verbatim.
        Schema::table('mes_oplog', function (Blueprint $table) {
            $table->string('method', 10)->default('POST')->after('type');
            $table->string('url', 200)->nullable()->after('method');
            $table->unsignedBigInteger('user_id')->nullable()->after('url');
            $table->timestamp('client_at')->nullable()->after('error');
        });

        // A vendor portal user is an ordinary user pinned to one supplier.
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('ven_id')->nullable()->after('status_id')->index();
        });

        // NPWP is required by e-Faktur (Coretax) and PPh 23 e-Bupot.
        Schema::table('m_contacts', function (Blueprint $table) {
            $table->string('npwp', 25)->nullable()->after('identity');
            $table->string('nik', 20)->nullable()->after('npwp');
        });

        // Withholding rate per vendor line — e-Bupot needs the object code + rate.
        Schema::table('prc_inv_main', function (Blueprint $table) {
            $table->string('wht23_code', 10)->nullable()->after('wht23');
            $table->decimal('wht23_rate', 6, 2)->default(0)->after('wht23_code');
        });
    }

    public function down(): void
    {
        Schema::table('prc_inv_main', fn (Blueprint $t) => $t->dropColumn(['wht23_code', 'wht23_rate']));
        Schema::table('m_contacts', fn (Blueprint $t) => $t->dropColumn(['npwp', 'nik']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('ven_id'));
        Schema::table('mes_oplog', fn (Blueprint $t) => $t->dropColumn(['method', 'url', 'user_id', 'client_at']));
    }
};
