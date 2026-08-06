<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class whs_out_det extends Model
{
    protected $connection = 'mysql';

    protected $table = 'whs_out_det';

    public $timestamps = false;

    protected $fillable = [
        'main_id', 'item_id', 'serial_code', 'qty', 'unit_cost', 'cost_center',
        'machine_id', 'asset_id', 'qty_returned', 'note',
    ];

    protected $casts = ['qty' => 'integer', 'qty_returned' => 'integer', 'unit_cost' => 'float'];

    public function item()
    {
        return $this->belongsTo(m_whs_item::class, 'item_id', 'id');
    }

    public function main()
    {
        return $this->belongsTo(whs_out_main::class, 'main_id', 'id');
    }

    /** Unit alat yang keluar lewat baris ini. */
    public function units()
    {
        return $this->hasMany(whs_tool_unit::class, 'out_det_id', 'id');
    }
}
