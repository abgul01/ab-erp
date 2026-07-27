<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maker-checker for moving a LOCKED (approved) MPS lot. A planner (mps edit)
 * submits a reschedule request; an approver (mps-approvals edit) approves it,
 * which applies the new date/machine, or rejects it. A lot with a PENDING
 * request blinks yellow on the board.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prd_mps_resched', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('mps_id');
            $t->date('from_date');
            $t->unsignedInteger('from_machine_id')->nullable();
            $t->date('to_date');
            $t->unsignedInteger('to_machine_id')->nullable();
            $t->string('reason', 200)->nullable();
            $t->string('status', 12)->default('PENDING'); // PENDING · APPROVED · REJECTED · CANCELLED
            $t->unsignedInteger('requested_by');
            $t->unsignedInteger('approved_by')->nullable();
            $t->string('decision_note', 200)->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->index(['mps_id', 'status']);
            $t->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prd_mps_resched');
    }
};
