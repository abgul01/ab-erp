<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prc_gr_reject extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prc_gr_reject';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'date',
        'gr_id',
        'ven_id',
        'item_id',
        'qty',
        'reason',
        'status',
    ];

    public function gr()
    {
        return $this->belongsTo(prc_gr_main::class, 'gr_id', 'id');
    }

    public function ven()
    {
        return $this->belongsTo(m_contacts::class, 'ven_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
