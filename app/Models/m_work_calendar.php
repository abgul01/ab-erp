<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One calendar day: whether the plant runs, and for how many hours. */
class m_work_calendar extends Model
{
    protected $connection = 'mysql';

    protected $table = 'm_work_calendar';

    protected $fillable = ['date', 'is_working', 'hours', 'note'];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'is_working' => 'boolean',
        'hours' => 'float',
    ];
}
