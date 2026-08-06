<?php

/*
 * Identity of the reporting company, used by the e-Faktur (Coretax) and
 * e-Bupot exports. These are filed under the company's own TIN, so they belong
 * in configuration rather than in any transaction table.
 */
return [
    'npwp' => env('TAX_NPWP', ''),
    'name' => env('TAX_NAME', env('APP_NAME', 'AB-ERP')),
    'address' => env('TAX_ADDRESS', ''),

    // NITKU — the branch identifier Coretax requires alongside the TIN.
    'idtku' => env('TAX_IDTKU', ''),

    // Default transaction code on a tax invoice: 01 = sale to a non-collector.
    'trx_code' => env('TAX_TRX_CODE', '01'),

    // Default PPh 23 object: 24-104-27 = jasa lain (subcontracting).
    'wht23_code' => env('TAX_WHT23_CODE', '24-104-27'),
    'wht23_rate' => (float) env('TAX_WHT23_RATE', 2),
];
