<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subcontract gets its own PO document (separate from the general Purchase
 * Order), plus a master of which goods each subcont vendor handles.
 */
return new class extends Migration
{
    public function up(): void
    {
        // master: what a subcont vendor can do (item, optional process, price)
        Schema::create('m_subcont_item', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('ven_id');
            $t->unsignedInteger('item_id');
            $t->unsignedInteger('process_id')->nullable();
            $t->decimal('price', 18, 2)->default(0);
            $t->tinyInteger('active')->default(1);
            $t->timestamps();
            $t->unique(['ven_id', 'item_id', 'process_id'], 'uq_subcont_vi');
            $t->index('ven_id');
        });

        Schema::create('sub_po_main', function (Blueprint $t) {
            $t->increments('id');
            $t->string('code', 50)->unique();
            $t->date('date');
            $t->unsignedInteger('ven_id');
            $t->unsignedInteger('user_id');
            $t->string('status', 20)->default('DRAFT'); // DRAFT|OPEN|CLOSED|CANCELLED
            $t->string('note', 200)->nullable();
            $t->timestamps();
        });

        Schema::create('sub_po_detail', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('main_id');
            $t->unsignedInteger('item_id');
            $t->unsignedInteger('process_id')->nullable();
            $t->integer('qty');
            $t->decimal('price', 18, 2)->default(0);
            $t->index('main_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_po_detail');
        Schema::dropIfExists('sub_po_main');
        Schema::dropIfExists('m_subcont_item');
    }
};
