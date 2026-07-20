<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prc_inv_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prc_inv_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'date',
        'ven_id',
        'po_id',
        'inv_no',
        'dpp',
        'vat',
        'wht23',
        'total',
        'tax_inv_no',
        'tax_inv_date',
        'due_date',
        'user_id',
        'status',
    ];

    public function ven()
    {
        return $this->belongsTo(m_contacts::class, 'ven_id', 'id');
    }

    public function po()
    {
        return $this->belongsTo(prc_po_main::class, 'po_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function detail()
    {
        return $this->hasMany(prc_inv_detail::class, 'main_id', 'id');
    }
}
