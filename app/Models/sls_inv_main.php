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
        'cus_id',
        'dpp_nilai_lain',
        'vat',
        'total',
        'tax_inv_no',
        'due_date',
        'status'
    ];

    public function cus()
    {
        return $this->belongsTo(m_contacts::class, 'cus_id', 'id');
    }
}
