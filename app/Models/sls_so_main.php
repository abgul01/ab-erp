<?php

namespace App\Models;

use App\Support\HasApproval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sls_so_main extends Model
{
    use HasApproval, HasFactory;

    protected $connection = 'mysql';

    protected $table = 'sls_so_main';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $fillable = [
        'code',
        'date',
        'cus_id',
        'cus_po_no',
        'po_date',
        'due_date',
        'currency_id',
        'user_id',
        'status',
        'note',
    ];

    public function cus()
    {
        return $this->belongsTo(m_contacts::class, 'cus_id', 'id');
    }

    public function currency()
    {
        return $this->belongsTo(m_currency::class, 'currency_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function detail()
    {
        return $this->hasMany(sls_so_detail::class, 'main_id', 'id');
    }
}
