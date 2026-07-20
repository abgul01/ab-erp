<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prc_cost_detail extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prc_cost_detail';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'main_id',
        'cost_type',
        'descrip',
        'amount',
        'currency_id',
        'rate',
        'amount_idr',
    ];

    public function main()
    {
        return $this->belongsTo(prc_cost_main::class, 'main_id', 'id');
    }

    public function currency()
    {
        return $this->belongsTo(m_currency::class, 'currency_id', 'id');
    }
}
