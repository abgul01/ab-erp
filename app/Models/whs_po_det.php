<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class whs_po_det extends Model
{
    protected $connection = 'mysql';

    protected $table = 'whs_po_det';

    public $timestamps = false;

    protected $fillable = ['main_id', 'item_id', 'qty', 'qty_received', 'price', 'note'];

    protected $casts = ['qty' => 'integer', 'qty_received' => 'integer', 'price' => 'float'];

    public function item()
    {
        return $this->belongsTo(m_whs_item::class, 'item_id', 'id');
    }

    public function main()
    {
        return $this->belongsTo(whs_po_main::class, 'main_id', 'id');
    }
}
