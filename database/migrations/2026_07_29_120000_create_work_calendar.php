<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Working calendar and real machine capacity (PRD §4.2).
 *
 * Scheduling used to assume every non-weekend day is a full working day and
 * that every machine runs 16 hours on it. National holidays, cuti bersama and
 * plant shutdowns were invisible, so MPS packed lots onto days the factory is
 * shut and CRP compared the load against hours that do not exist.
 *
 * Two pieces fix that: a plant calendar saying which dates are worked and for
 * how long, and a per-machine daily capacity for the machines that do not run
 * the standard two shifts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_work_calendar', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->boolean('is_working')->default(true);
            // Productive hours the plant runs that day: 16 = two shifts,
            // 8 = one shift, 24 = three. Ignored when is_working is false.
            $table->decimal('hours', 5, 2)->default(16);
            $table->string('note', 100)->nullable();
            $table->timestamps();

            $table->index(['date', 'is_working']);
        });

        Schema::table('m_machine', function (Blueprint $table) {
            // Per-machine override; 0 means "follow the plant calendar".
            $table->decimal('daily_hours', 5, 2)->default(0)->after('kwh');
        });
    }

    public function down(): void
    {
        Schema::table('m_machine', fn (Blueprint $t) => $t->dropColumn('daily_hours'));
        Schema::dropIfExists('m_work_calendar');
    }
};
