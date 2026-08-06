<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Tolerance for one parameter on one item — what makes a reading pass or fail. */
class m_item_inspection extends Model
{
    protected $connection = 'mysql';

    protected $table = 'm_item_inspection';

    protected $fillable = ['item_id', 'param_id', 'nominal', 'min_value', 'max_value', 'mandatory'];
}
