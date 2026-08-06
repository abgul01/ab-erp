<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class npd_milestone extends Model
{
    protected $connection = 'mysql';

    protected $table = 'npd_milestone';

    protected $fillable = ['main_id', 'name', 'planned_date', 'actual_date', 'status'];

    protected $casts = ['planned_date' => 'date:Y-m-d', 'actual_date' => 'date:Y-m-d'];
}
