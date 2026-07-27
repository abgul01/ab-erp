<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales Order gains the dates and flags the sales desk actually works with:
 * when the customer PO was received, an order-level due date and remark, plus
 * per line a remark, a local-material flag, and independent PPN / PPh ticks
 * (either, both, or neither may apply to a line).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sls_so_main', function (Blueprint $t) {
            $t->date('po_date')->nullable()->after('cus_po_no');   // tanggal PO diterima
            $t->date('due_date')->nullable()->after('po_date');    // due date order
            $t->string('note', 200)->nullable()->after('status');  // keterangan
        });

        Schema::table('sls_so_detail', function (Blueprint $t) {
            $t->tinyInteger('local_mat')->default(0)->after('tax_id'); // 1 = material lokal
            $t->tinyInteger('ppn')->default(0)->after('local_mat');
            $t->tinyInteger('pph')->default(0)->after('ppn');
            $t->string('note', 200)->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('sls_so_main', function (Blueprint $t) {
            $t->dropColumn(['po_date', 'due_date', 'note']);
        });
        Schema::table('sls_so_detail', function (Blueprint $t) {
            $t->dropColumn(['local_mat', 'ppn', 'pph', 'note']);
        });
    }
};
