<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class acc_ap_pay_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'acc_ap_pay_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'ven_id',
        'status'
    ];

    public function ven()
    {
        return $this->belongsTo(m_contacts::class, 'ven_id', 'id');
    }
}
