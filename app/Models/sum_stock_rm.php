<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sum_stock_rm extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'sum_stock_rm';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'main_id',
        'item_id',
        'qty',
        'length_serial',
        'length_total',
        'rack_id',
        'weight_base'
    ];

    public function main()
    {
        return $this->belongsTo(sum_stock_main::class, 'main_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function rack()
    {
        return $this->belongsTo(m_rack::class, 'rack_id', 'id');
    }
}
