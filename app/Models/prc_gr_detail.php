<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prc_gr_detail extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prc_gr_detail';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'id_prim',
        'po_id',
        'item_id',
        'quota_id',
        'hs_code',
        'qty',
        'length',
        'weight',
        'w_total',
        'note',
    ];

    public function main()
    {
        return $this->belongsTo(prc_gr_main::class, 'id_prim', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function quota()
    {
        return $this->belongsTo(m_quota::class, 'quota_id', 'id');
    }

    public function po()
    {
        return $this->belongsTo(prc_po_main::class, 'po_id', 'id');
    }

    public function serials()
    {
        return $this->hasMany(prc_gr_serial::class, 'det_id', 'id');
    }
}
