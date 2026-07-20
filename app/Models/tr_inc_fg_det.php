<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_inc_fg_det extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_inc_fg_det';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'main_id',
        'pal_pro_code',
        'item_id',
        'cut_id',
        'qty',
        'wip_id'
    ];

    public function main()
    {
        return $this->belongsTo(tr_inc_fg_main::class, 'main_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function cut()
    {
        return $this->belongsTo(tr_cut_main::class, 'cut_id', 'id');
    }

    public function wip()
    {
        return $this->belongsTo(wip::class, 'wip_id', 'id');
    }
}
