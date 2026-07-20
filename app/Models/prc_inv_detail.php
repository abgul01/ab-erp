<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prc_inv_detail extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prc_inv_detail';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'main_id',
        'gr_detail_id',
        'qty',
        'price',
        'amount',
    ];

    public function main()
    {
        return $this->belongsTo(prc_inv_main::class, 'main_id', 'id');
    }

    public function grDetail()
    {
        return $this->belongsTo(prc_gr_detail::class, 'gr_detail_id', 'id');
    }
}
