<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sls_inv_detail extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'sls_inv_detail';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;   // sls_inv_detail has no created_at/updated_at

    protected $fillable = [
        'main_id',
        'do_detail_id',
        'item_id',
        'qty',
        'price',
        'amount',
    ];

    public function main()
    {
        return $this->belongsTo(sls_inv_main::class, 'main_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
