<?php

namespace App\Models;

use App\Support\HasApproval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_quota extends Model
{
    use HasApproval;
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'm_quota';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $fillable = [
        'status',
        'code',
        'descrip',
        'hs_code',
        'total_ton',
        'valid_from',
        'valid_to',
        'active',
    ];

    public function items()
    {
        return $this->hasMany(m_quota_item::class, 'quota_id', 'id');
    }

    public function txns()
    {
        return $this->hasMany(prc_quota_txn::class, 'quota_id', 'id');
    }

    public function prc_po_main()
    {
        return $this->hasMany(prc_po_main::class, 'quota_id', 'id');
    }
}
