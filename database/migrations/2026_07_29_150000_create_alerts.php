<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operational alerts (LLD §2.2: QuotaAlertJob, MinStockAlertJob).
 *
 * Import quota is hard-blocked when a PO would exceed it, but the buyer only
 * finds out at the moment the PO is refused — too late to arrange anything.
 * The same is true of stock falling under its minimum. These are the warnings
 * that give someone time to act.
 *
 * Rows are keyed so the same condition raises one open alert, not one per run:
 * an alert that repeats hourly is an alert nobody reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_alert', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30)->index();          // QUOTA | MIN_STOCK
            $table->string('severity', 10)->default('WARNING');  // INFO | WARNING | CRITICAL
            $table->string('ref_type', 30)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('title', 150);
            $table->string('message', 400);
            $table->decimal('value', 18, 3)->nullable();
            $table->decimal('threshold', 18, 3)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamps();

            // One open alert per condition; re-running the check updates it.
            $table->unique(['type', 'ref_type', 'ref_id'], 'uq_alert_condition');
            $table->index(['resolved_at', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_alert');
    }
};
