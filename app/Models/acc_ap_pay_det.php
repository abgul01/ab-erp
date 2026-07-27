<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class acc_ap_pay_det extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'acc_ap_pay_det';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;


    protected $fillable = [
        'main_id',
        'inv_id',
        'amount',
    ];

    public function invoice()
    {
        return $this->belongsTo(prc_inv_main::class, 'inv_id', 'id');
    }
}
