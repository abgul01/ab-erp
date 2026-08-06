<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class whs_inc_det extends Model
{
    protected $connection = 'mysql';

    protected $table = 'whs_inc_det';

    public $timestamps = false;

    protected $fillable = ['main_id', 'po_det_id', 'item_id', 'serial_code', 'qty', 'unit_cost', 'note'];

    protected $casts = ['qty' => 'integer', 'unit_cost' => 'float'];

    public function item()
    {
        return $this->belongsTo(m_whs_item::class, 'item_id', 'id');
    }
}
