<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Engineering Change Notice (PRD §4.3).
 *
 * Until now an engineer could change a dimension on an item, a material on a
 * BOM or a step on a routing, and the only trace was the row's updated_at. In a
 * running factory that is not enough: production needs to know what changed,
 * who agreed to it, and from which date it applies — because parts made before
 * the change are not defective, they are the previous revision.
 *
 * So the change is written down before it happens. Each line records the field,
 * the value it had when the notice was raised, and the value it is to take. The
 * master is only touched when the notice is approved and its effective date has
 * arrived, and the revision number of what changed is bumped at that moment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eng_ecn_main', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->date('date');
            $table->string('change_type', 10);           // ITEM | BOM | ROUTING
            $table->unsignedBigInteger('item_id')->index();   // the part being changed
            $table->string('reason', 400);
            $table->string('impact', 400)->nullable();
            // Parts made before this date belong to the previous revision.
            $table->date('effective_date');
            $table->string('status', 20)->default('DRAFT'); // DRAFT|SUBMITTED|APPROVED|APPLIED|REJECTED
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'effective_date']);
        });

        Schema::create('eng_ecn_det', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();
            $table->string('action', 10)->default('UPDATE');  // UPDATE | ADD | REMOVE
            $table->string('target_table', 30);
            // Null for ADD: the row does not exist yet when the notice is raised.
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('field', 40)->nullable();
            // Values are kept as text because one notice can touch a dimension,
            // a material id and a tolerance string in the same breath.
            $table->string('old_value', 150)->nullable();
            $table->string('new_value', 150)->nullable();
            $table->string('note', 200)->nullable();
        });

        /*
         * Revision on the masters an ECN can change. A drawing that says "rev 3"
         * has to match something in the system, and "which revision was this lot
         * built to" is unanswerable without it.
         */
        foreach (['m_item', 'm_bom', 'm_process_main'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->unsignedSmallInteger('rev')->default(0)->after('id');
                $table->date('rev_date')->nullable()->after('rev');
            });
        }
    }

    public function down(): void
    {
        foreach (['m_item', 'm_bom', 'm_process_main'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->dropColumn(['rev', 'rev_date']));
        }

        Schema::dropIfExists('eng_ecn_det');
        Schema::dropIfExists('eng_ecn_main');
    }
};
