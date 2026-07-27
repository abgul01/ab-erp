<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MES execution (Fase 3). Work flows through the FG routing in two scan modes:
 *   seq 1 (cutting)  → operator scans an RM SERIAL booked to the WO; the cut
 *                      pieces come off as a new PALLET.
 *   seq > 1          → operator scans that PALLET; it advances one routing step.
 *
 * mes_pallet = the WIP carrier (where a batch currently sits in the routing).
 * mes_exec   = one reported operation (good/NG qty on a machine, date, shift).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mes_pallet', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->unsignedInteger('wo_id');
            $t->unsignedInteger('item_id');            // FG being produced
            $t->unsignedTinyInteger('seq')->default(0); // last completed routing step
            $t->unsignedInteger('proc_id')->nullable(); // last completed process
            $t->integer('qty')->default(0);             // good pieces currently on it
            $t->string('status', 10)->default('OPEN');  // OPEN · FINISHED · SCRAP
            $t->timestamps();
            $t->index(['wo_id', 'status']);
        });

        Schema::create('mes_exec', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('wo_id');
            $t->unsignedTinyInteger('seq');             // routing step performed
            $t->unsignedInteger('proc_id');
            $t->unsignedInteger('machine_id')->nullable();
            $t->unsignedBigInteger('pallet_id')->nullable();
            $t->string('in_type', 8);                  // SERIAL (cutting) · PALLET
            $t->string('in_ref', 50)->nullable();      // serial_id or pallet code
            $t->date('date');
            $t->string('shift', 10)->nullable();
            $t->string('operator', 50)->nullable();
            $t->integer('qty_good')->default(0);
            $t->integer('qty_ng')->default(0);
            $t->double('length_used')->default(0);     // cutting only
            $t->string('note', 200)->nullable();
            $t->unsignedInteger('user_id')->nullable();
            $t->timestamps();
            $t->index(['wo_id', 'seq']);
            $t->index(['machine_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mes_exec');
        Schema::dropIfExists('mes_pallet');
    }
};
