<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class prc_contract_det extends Model
{
    protected $connection = 'mysql';

    protected $table = 'prc_contract_det';

    public $timestamps = false;

    protected $fillable = [
        'main_id', 'item_id', 'price', 'moq', 'order_lot',
        'lead_time_days', 'commit_qty', 'note',
    ];

    protected $casts = [
        'price' => 'float',
        'moq' => 'integer',
        'order_lot' => 'integer',
        'lead_time_days' => 'integer',
        'commit_qty' => 'integer',
    ];

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
