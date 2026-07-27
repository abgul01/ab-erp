<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_pricelist_det extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_pricelist_det';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;   // m_pricelist_det has no created_at/updated_at

    protected $fillable = [
        'main_id',
        'item_id',
        'price',
        'currency_id',
        'valid_from',
        'valid_to',
        'min_qty',
    ];


    public function main()
    {
        return $this->belongsTo(m_pricelist_main::class, 'main_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function currency()
    {
        return $this->belongsTo(m_currency::class, 'currency_id', 'id');
    }
}
