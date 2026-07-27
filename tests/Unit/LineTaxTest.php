<?php

use App\Support\LineTax;

/** PPN uses the tariff's DPP "nilai lain" factor; PPh is withheld on the gross. */
$ppn = (object) ['dpp_factor' => 0.916667, 'rate_pct' => 12.0];   // PPN-DN 11/12
$pph = (object) ['dpp_factor' => 1.0, 'rate_pct' => 2.0];         // PPh 23

it('computes DPP nilai lain and PPN on it', function () use ($ppn) {
    $r = LineTax::compute(120000, $ppn, null);

    expect($r['dpp'])->toBeMoney(110000.04)     // 120000 × 11/12
        ->and($r['ppn_value'])->toBeMoney(13200.00)   // dpp × 12%
        ->and($r['pph_value'])->toBeMoney(0.0);
});

it('withholds PPh on the gross subtotal, not the DPP', function () use ($ppn, $pph) {
    $r = LineTax::compute(120000, $ppn, $pph);

    expect($r['pph_value'])->toBeMoney(2400.00);   // 120000 × 2%
});

it('keeps the gross as base when there is no PPN', function () {
    $r = LineTax::compute(50000, null, null);

    expect($r['dpp'])->toBeMoney(50000.0)
        ->and($r['ppn_value'])->toBeMoney(0.0);
});
