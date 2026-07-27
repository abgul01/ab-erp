<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable routing templates. Instead of re-entering the process sequence on
 * every item, a "Master Process Main" defines an ordered set of processes once
 * (m_process_main + _det), and items point at it via m_bom_pro.process_main_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_process_main', function (Blueprint $t) {
            $t->increments('id');
            $t->string('code', 50)->unique();
            $t->string('name', 100);
            $t->tinyInteger('active')->default(1);
            $t->timestamps();
        });

        Schema::create('m_process_main_det', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('main_id');
            $t->unsignedInteger('proc_id');
            $t->tinyInteger('sequence')->nullable();
            $t->timestamps();
            $t->index('main_id');
            $t->index('proc_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_process_main_det');
        Schema::dropIfExists('m_process_main');
    }
};
