<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sls_so_detail extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'sls_so_detail';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'main_id',
        'item_id',
        'qty',
        'price',
        'pricelist_det_id',
        'tax_id',
        'due_date',
        'qty_delivered',
    ];

    public function main()
    {
        return $this->belongsTo(sls_so_main::class, 'main_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function tax()
    {
        return $this->belongsTo(m_tax::class, 'tax_id', 'id');
    }
}
