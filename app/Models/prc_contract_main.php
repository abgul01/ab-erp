<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Kontrak berperiode dengan satu vendor; harganya mengunci master syarat beli. */
class prc_contract_main extends Model
{
    protected $connection = 'mysql';

    protected $table = 'prc_contract_main';

    protected $fillable = [
        'code', 'date', 'ven_id', 'ref_no', 'quot_id',
        'valid_from', 'valid_to', 'status', 'note', 'user_id',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'valid_from' => 'date:Y-m-d',
        'valid_to' => 'date:Y-m-d',
    ];

    public function detail()
    {
        return $this->hasMany(prc_contract_det::class, 'main_id', 'id');
    }

    public function ven()
    {
        return $this->belongsTo(m_contacts::class, 'ven_id', 'id');
    }

    public function isRunning(): bool
    {
        return $this->status === 'ACTIVE'
            && $this->valid_from->lte(now()->startOfDay())
            && $this->valid_to->gte(now()->startOfDay());
    }
}
