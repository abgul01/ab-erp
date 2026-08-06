<?php

namespace App\Models;

use App\Support\HasApproval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prc_po_main extends Model
{
    use HasApproval, HasFactory;

    protected $connection = 'mysql';

    protected $table = 'prc_po_main';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $fillable = [
        'code',
        'date',
        'po_type',
        'source',
        'ven_id',
        'quota_id',
        'currency_id',
        'rate',
        'top_days',
        'eta',
        'user_id',
        'status',
    ];

    public function ven()
    {
        return $this->belongsTo(m_contacts::class, 'ven_id', 'id');
    }

    public function quota()
    {
        return $this->belongsTo(m_quota::class, 'quota_id', 'id');
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
        return $this->hasMany(prc_po_detail::class, 'main_id', 'id');
    }

    public function prc_cost_main()
    {
        return $this->hasMany(prc_cost_main::class, 'po_id', 'id');
    }
}
