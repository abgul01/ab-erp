<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class whs_ret_det extends Model
{
    protected $connection = 'mysql';

    protected $table = 'whs_ret_det';

    public $timestamps = false;

    protected $fillable = ['main_id', 'out_det_id', 'tool_unit_id', 'condition', 'note'];

    public function unit()
    {
        return $this->belongsTo(whs_tool_unit::class, 'tool_unit_id', 'id');
    }
}
