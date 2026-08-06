<?php

use Illuminate\Support\Facades\DB;

/**
 * Kosakata status setiap dokumen harus tertutup.
 *
 * Status yang tidak dikenal alur kerjanya membuat dokumen terkunci tanpa ada
 * yang menyadari: tidak ada error, tidak ada pesan — dokumennya hanya tidak
 * bisa dikerjakan lagi. Itu yang terjadi pada seratus Delivery Order demo yang
 * berstatus "DELIVERED", sebuah nilai yang tidak pernah dihasilkan maupun
 * diterima kode mana pun.
 *
 * Sumber paling sering masuknya status karangan adalah seeder, karena ia
 * menulis langsung ke tabel tanpa melewati controller yang menjaga transisinya.
 */
dataset('dokumen', [
    // [tabel, status yang sah]
    'delivery order' => ['sls_do_main', ['DRAFT', 'SHIPPED', 'RECEIVED', 'CANCELLED']],
    'shipping order' => ['sls_ship_main', ['DRAFT', 'DISPATCHED', 'DELIVERED', 'CANCELLED']],
    'packing list' => ['sls_pack_main', ['DRAFT', 'FINAL', 'CANCELLED']],
    'purchase order' => ['prc_po_main', ['DRAFT', 'SUBMITTED', 'APPROVED', 'OPEN', 'INPROGRESS', 'CLOSE', 'CANCELLED']],
    'purchase requisition' => ['prc_pr_main', ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'CLOSED', 'CANCELLED']],
    'sales order' => ['sls_so_main', ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'CLOSED', 'CANCELLED']],
    'jurnal' => ['acc_journal_main', ['POSTED', 'REVERSED']],
    'penawaran vendor' => ['prc_quot_main', ['DRAFT', 'RECEIVED', 'SELECTED', 'REJECTED']],
    'kontrak vendor' => ['prc_contract_main', ['DRAFT', 'ACTIVE', 'EXPIRED', 'CANCELLED']],
    'proyek NPD' => ['npd_project', ['DRAFT', 'RUNNING', 'ON_HOLD', 'HANDOVER', 'CLOSED', 'CANCELLED']],
    'PPAP' => ['npd_ppap_main', ['DRAFT', 'SUBMITTED', 'INTERIM', 'APPROVED', 'REJECTED']],
    'penerimaan WHS' => ['whs_inc_main', ['DRAFT', 'POSTED']],
    'pengeluaran WHS' => ['whs_out_main', ['DRAFT', 'POSTED']],
]);

it('hanya memakai status yang dikenal alur kerjanya', function (string $table, array $allowed) {
    $found = DB::table($table)->distinct()->pluck('status')->filter()->all();
    $stray = array_diff($found, $allowed);

    expect($stray)->toBe(
        [],
        "{$table} memakai status di luar kosakatanya: ".implode(', ', $stray)
            .'. Dokumen dengan status seperti ini terkunci — tidak ada transisi yang menerimanya.'
    );
})->with('dokumen');

it('meninggalkan Delivery Order dalam status yang bisa dikerjakan', function () {
    // Regresi langsung atas bug yang ditemukan: seluruh DO demo berstatus
    // DELIVERED, yang membuat ship(), receive(), dan cancel() semuanya menolak.
    $stuck = DB::table('sls_do_main')->where('status', 'DELIVERED')->count();

    expect($stuck)->toBe(0);
});
