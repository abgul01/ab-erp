<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor Quotation & Contract (PRD §4.7).
 *
 * `m_supplier_item` sudah menjawab "beli ke siapa, harga berapa" — tetapi tidak
 * menjawab dari mana harga itu berasal. Angkanya muncul begitu saja di master,
 * tanpa penawaran yang bisa ditunjuk dan tanpa masa berlaku yang disepakati.
 * Saat pemasok menaikkan harga, tidak ada pembanding; saat auditor bertanya
 * kenapa vendor ini yang dipilih padahal ada yang lebih murah, tidak ada
 * jawabannya.
 *
 * Dua dokumen menutup itu:
 *
 *   Quotation — penawaran yang masuk dari satu vendor untuk sejumlah material,
 *   dengan masa berlaku. Beberapa quotation untuk material yang sama bisa
 *   dibandingkan berdampingan, dan yang dipilih menjadi syarat beli di
 *   `m_supplier_item` — lengkap dengan tautan balik ke penawarannya.
 *
 *   Contract — kesepakatan berperiode dengan satu vendor. Harga kontrak
 *   mengunci `m_supplier_item` selama masa berlakunya, sehingga penawaran baru
 *   yang lebih mahal tidak diam-diam menggantikannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prc_quot_main', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->date('date');
            $table->unsignedBigInteger('ven_id')->index();
            $table->string('ref_no', 50)->nullable();          // nomor surat penawaran vendor
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            // DRAFT | RECEIVED | SELECTED | REJECTED
            $table->string('status', 10)->default('DRAFT');
            $table->string('note', 300)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'date']);
        });

        Schema::create('prc_quot_det', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();
            $table->unsignedBigInteger('item_id')->index();
            $table->decimal('price', 18, 4)->default(0);
            $table->unsignedInteger('moq')->default(0);
            $table->unsignedInteger('order_lot')->default(0);
            $table->unsignedSmallInteger('lead_time_days')->default(0);
            $table->string('note', 150)->nullable();
            /*
             * Terisi saat baris ini dipilih menjadi syarat beli. Menyimpannya di
             * sini, bukan hanya di master, membuat penawaran yang kalah tetap
             * bisa ditunjukkan sebagai pembanding.
             */
            $table->boolean('selected')->default(false);
            $table->timestamp('selected_at')->nullable();

            $table->unique(['main_id', 'item_id']);
        });

        Schema::create('prc_contract_main', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->date('date');
            $table->unsignedBigInteger('ven_id')->index();
            $table->string('ref_no', 50)->nullable();          // nomor kontrak pihak vendor
            $table->unsignedBigInteger('quot_id')->nullable(); // penawaran yang mendasarinya
            $table->date('valid_from');
            $table->date('valid_to');
            // DRAFT | ACTIVE | EXPIRED | CANCELLED
            $table->string('status', 10)->default('DRAFT');
            $table->string('note', 300)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'valid_to']);
        });

        Schema::create('prc_contract_det', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_id')->index();
            $table->unsignedBigInteger('item_id')->index();
            $table->decimal('price', 18, 4)->default(0);
            $table->unsignedInteger('moq')->default(0);
            $table->unsignedInteger('order_lot')->default(0);
            $table->unsignedSmallInteger('lead_time_days')->default(0);
            // Komitmen volume selama masa kontrak, bila ada.
            $table->unsignedInteger('commit_qty')->default(0);
            $table->string('note', 150)->nullable();

            $table->unique(['main_id', 'item_id']);
        });

        Schema::table('m_supplier_item', function (Blueprint $table) {
            /*
             * Asal-usul syarat beli. Tanpa ini, harga di master adalah angka
             * tanpa riwayat — dan pertanyaan "kenapa segini" hanya bisa dijawab
             * oleh orang yang kebetulan masih ingat.
             */
            $table->unsignedBigInteger('quot_det_id')->nullable()->after('supplier_part_no');
            $table->unsignedBigInteger('contract_id')->nullable()->after('quot_det_id');
        });
    }

    public function down(): void
    {
        Schema::table('m_supplier_item', fn (Blueprint $t) => $t->dropColumn(['quot_det_id', 'contract_id']));

        foreach (['prc_contract_det', 'prc_contract_main', 'prc_quot_det', 'prc_quot_main'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
