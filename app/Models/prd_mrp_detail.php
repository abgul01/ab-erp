<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prd_mrp_detail extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prd_mrp_detail';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;   // detail has no timestamps

    protected $fillable = [
        'main_id',
        'item_id',
        'period',
        'gross_req',
        'onhand',
        'open_po',
        'open_wo',
        'net_req',
        'net_req_kg',
        'suggestion',
    ];

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
