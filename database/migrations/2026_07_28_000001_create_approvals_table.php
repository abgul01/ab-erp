<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->string('doc_type', 30);
            $table->unsignedBigInteger('doc_id');
            $table->tinyInteger('level');
            $table->string('required_role', 50)->comment('permission key, e.g. po.approve');
            $table->string('status', 15)->default('PENDING');
            $table->unsignedBigInteger('acted_by')->nullable();
            $table->dateTime('acted_at')->nullable();
            $table->string('note', 300)->nullable();
            $table->timestamps();

            $table->index(['doc_type', 'doc_id']);
            $table->index(['status', 'required_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
