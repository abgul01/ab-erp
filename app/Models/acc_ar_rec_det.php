<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class acc_ar_rec_det extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'acc_ar_rec_det';
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
        return $this->belongsTo(sls_inv_main::class, 'inv_id', 'id');
    }
}
