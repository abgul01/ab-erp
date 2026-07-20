<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prc_cost_alloc extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prc_cost_alloc';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'main_id',
        'gr_detail_id',
        'serial_id',
        'amount',
        'unit_cost_kg',
    ];

    public function main()
    {
        return $this->belongsTo(prc_cost_main::class, 'main_id', 'id');
    }

    public function grDetail()
    {
        return $this->belongsTo(prc_gr_detail::class, 'gr_detail_id', 'id');
    }
}
