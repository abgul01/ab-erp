<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Deliverable baku yang wajib ada di sebuah fase sebelum gate-nya boleh diajukan. */
class npd_deliverable_std extends Model
{
    protected $connection = 'mysql';

    protected $table = 'npd_deliverable_std';

    protected $fillable = ['phase_id', 'code', 'name', 'mandatory', 'sort'];

    protected $casts = ['mandatory' => 'boolean'];
}
