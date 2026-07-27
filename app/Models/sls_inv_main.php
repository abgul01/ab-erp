<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sls_inv_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'sls_inv_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'date',
        'cus_id',
        'dpp',
        'dpp_nilai_lain',
        'vat',
        'total',
        'tax_inv_no',
        'due_date',
        'user_id',
        'status',
    ];

    public function detail()
    {
        return $this->hasMany(sls_inv_detail::class, 'main_id', 'id');
    }

    public function cus()
    {
        return $this->belongsTo(m_contacts::class, 'cus_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
