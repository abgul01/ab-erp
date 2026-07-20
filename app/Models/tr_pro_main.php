<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_pro_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_pro_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'wip_id',
        'cut_id',
        'item_id',
        'user_id',
        'process_id',
        'pallet_code',
        'no_dp',
        'qty_half',
        'qty_full',
        'subcon_code',
        'date',
        'start_time',
        'end_time',
        'client_uuid'
    ];

    public function wip()
    {
        return $this->belongsTo(wip::class, 'wip_id', 'id');
    }

    public function cut()
    {
        return $this->belongsTo(tr_cut_main::class, 'cut_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function process()
    {
        return $this->belongsTo(m_process::class, 'process_id', 'id');
    }

    public function tr_ab_pro()
    {
        return $this->hasMany(tr_ab_pro::class, 'pro_id', 'id');
    }

    public function tr_dt_pro_main()
    {
        return $this->hasMany(tr_dt_pro_main::class, 'pro_id', 'id');
    }

    public function detail()
    {
        return $this->hasMany(tr_pro_detail::class, 'main_id', 'id');
    }

    public function tr_pro_pal_pr()
    {
        return $this->hasMany(tr_pro_pal_pr::class, 'pro_id', 'id');
    }
}
