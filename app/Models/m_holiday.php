<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One national / joint (cuti bersama) / company holiday.
 *
 * Indonesian holidays come from the annual SKB 3 Menteri decree and cannot be
 * derived from any calendar — they are government decisions. Recording them
 * here is what lets the working calendar generator apply them instead of a
 * planner typing them by hand every month.
 *
 * A holiday is not automatically a shutdown: many plants keep running on cuti
 * bersama with a skeleton crew, so `is_working` + `hours` decide what the
 * working calendar actually does with it.
 */
class m_holiday extends Model
{
    protected $connection = 'mysql';

    protected $table = 'm_holiday';

    protected $fillable = [
        'date',
        'name',
        'type',        // NASIONAL | CUTI_BERSAMA | PERUSAHAAN
        'is_working',
        'hours',
        'active',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'is_working' => 'boolean',
        'hours' => 'float',
        'active' => 'boolean',
    ];
}
