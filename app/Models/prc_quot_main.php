<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Penawaran harga yang masuk dari satu vendor. */
class prc_quot_main extends Model
{
    protected $connection = 'mysql';

    protected $table = 'prc_quot_main';

    protected $fillable = [
        'code', 'date', 'ven_id', 'ref_no', 'currency_id',
        'valid_from', 'valid_to', 'status', 'note', 'user_id',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'valid_from' => 'date:Y-m-d',
        'valid_to' => 'date:Y-m-d',
    ];

    public function detail()
    {
        return $this->hasMany(prc_quot_det::class, 'main_id', 'id');
    }

    public function ven()
    {
        return $this->belongsTo(m_contacts::class, 'ven_id', 'id');
    }

    /** Penawaran yang masa berlakunya sudah lewat tidak boleh dipilih lagi. */
    public function isExpired(): bool
    {
        return $this->valid_to && $this->valid_to->isBefore(now()->startOfDay());
    }
}
