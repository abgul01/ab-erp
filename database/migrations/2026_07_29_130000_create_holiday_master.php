<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Holiday master (PRD §4.2, pendukung Working Calendar).
 *
 * Indonesian public holidays are set by SKB 3 Menteri each year and cannot be
 * derived — Islamic dates shift against the Gregorian calendar and cuti bersama
 * is a government decision. Without somewhere to record them, every calendar
 * generation depends on a planner remembering, and a forgotten Idul Fitri means
 * production scheduled onto a shut factory with nothing to flag it.
 *
 * The three types are kept apart because plants treat them differently: a
 * national holiday closes the plant, cuti bersama often runs a skeleton crew,
 * and a company shutdown appears on no government calendar at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_holiday', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('name', 100);
            $table->string('type', 20)->default('NASIONAL');   // NASIONAL | CUTI_BERSAMA | PERUSAHAAN
            // Some plants keep running on cuti bersama with fewer people, so a
            // holiday is not automatically a non-working day.
            $table->boolean('is_working')->default(false);
            $table->decimal('hours', 5, 2)->default(0);        // productive hours if it does run
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['date', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_holiday');
    }
};
