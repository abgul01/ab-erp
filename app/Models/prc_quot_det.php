<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class prc_quot_det extends Model
{
    protected $connection = 'mysql';

    protected $table = 'prc_quot_det';

    public $timestamps = false;

    protected $fillable = [
        'main_id', 'item_id', 'price', 'moq', 'order_lot',
        'lead_time_days', 'note', 'selected', 'selected_at',
    ];

    protected $casts = [
        'price' => 'float',
        'moq' => 'integer',
        'order_lot' => 'integer',
        'lead_time_days' => 'integer',
        'selected' => 'boolean',
        'selected_at' => 'datetime',
    ];

    public function main()
    {
        return $this->belongsTo(prc_quot_main::class, 'main_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
