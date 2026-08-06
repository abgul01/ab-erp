<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One box of a packing list. */
class sls_pack_det extends Model
{
    protected $connection = 'mysql';

    protected $table = 'sls_pack_det';

    public $timestamps = false;

    protected $fillable = [
        'main_id', 'box_no', 'do_detail_id', 'item_id', 'qty',
        'net_weight', 'gross_weight', 'dimension', 'note',
    ];

    protected $casts = ['net_weight' => 'float', 'gross_weight' => 'float'];

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
