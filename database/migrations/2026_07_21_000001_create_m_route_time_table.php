<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cycle-time master (routing time): how long one piece of an item takes at a
 * given process on a given machine, plus a priority so the scheduler can pick
 * the preferred machine. Feeds MPS auto-scheduling (capacity per day) and CRP.
 * Additive — does not touch the existing routing table (m_bom_pro_det).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_route_time', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('item_id');            // the item being produced (FG)
            $t->unsignedInteger('proc_id');            // m_process
            $t->unsignedInteger('machine_id')->nullable(); // preferred m_machine
            $t->double('cycle_sec')->default(0);       // runtime per piece (seconds)
            $t->double('setup_min')->default(0);       // setup per batch (minutes)
            $t->unsignedTinyInteger('priority')->default(1); // 1 = most preferred machine
            $t->tinyInteger('active')->default(1);
            $t->timestamps();
            $t->index('item_id');
            $t->index(['item_id', 'proc_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_route_time');
    }
};
