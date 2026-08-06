<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One counted line: what the system held, what was found, and the gap. */
class wh_adj_detail extends Model
{
    protected $connection = 'mysql';

    protected $table = 'wh_adj_detail';

    public $timestamps = false;

    protected $fillable = [
        'main_id', 'item_id', 'serial_id',
        'qty_system', 'qty_counted', 'qty_diff', 'unit_cost', 'note',
    ];

    protected $casts = [
        'qty_system' => 'float',
        'qty_counted' => 'float',
        'qty_diff' => 'float',
        'unit_cost' => 'float',
    ];

    public function main()
    {
        return $this->belongsTo(wh_adj_main::class, 'main_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
