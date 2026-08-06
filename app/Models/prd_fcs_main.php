<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class prd_fcs_main extends Model
{
    protected $connection = 'mysql';

    protected $table = 'prd_fcs_main';

    protected $fillable = [
        'wo_id',
        'fg_item_id',
        'qty_planned',
        'qty_good',
        'status',
        'traceability',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'traceability' => 'json',
        'approved_at' => 'datetime',
    ];

    public function wo()
    {
        return $this->belongsTo(prd_wo_main::class, 'wo_id');
    }

    public function fgItem()
    {
        return $this->belongsTo(m_item::class, 'fg_item_id');
    }
}
