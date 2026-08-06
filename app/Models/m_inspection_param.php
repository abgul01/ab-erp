<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One measurable characteristic an incoming item can be checked on. */
class m_inspection_param extends Model
{
    protected $connection = 'mysql';

    protected $table = 'm_inspection_param';

    protected $fillable = ['code', 'name', 'uom', 'method', 'active'];
}
