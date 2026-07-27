<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class acc_ar_rec_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'acc_ar_rec_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'date',
        'cus_id',
        'amount',
        'user_id',
        'status',
    ];

    public function cus()
    {
        return $this->belongsTo(m_contacts::class, 'cus_id', 'id');
    }
    public function detail()
    {
        return $this->hasMany(acc_ar_rec_det::class, 'main_id', 'id');
    }
}
