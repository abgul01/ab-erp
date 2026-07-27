<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_cut_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_cut_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'user_id',
        'wip_id',
        'no_dp',
        'item_id',
        'process_id',
        'date',
        'shift_id',
        'subcont',
        'sub_code',
        'repair',
        'client_uuid'
    ];

    public function wip()
    {
        return $this->belongsTo(wip::class, 'wip_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function process()
    {
        return $this->belongsTo(m_process::class, 'process_id', 'id');
    }

    public function tr_ab_cut_main()
    {
        return $this->hasMany(tr_ab_cut_main::class, 'cut_id', 'id');
    }

    public function tr_ab_pro()
    {
        return $this->hasMany(tr_ab_pro::class, 'cut_id', 'id');
    }

    public function detail()
    {
        return $this->hasMany(tr_cut_detail::class, 'main_id', 'id');
    }

    public function tr_cut_pal_pr()
    {
        return $this->hasMany(tr_cut_pal_pr::class, 'cut_id', 'id');
    }

    public function tr_dt_cut_main()
    {
        return $this->hasMany(tr_dt_cut_main::class, 'cut_id', 'id');
    }

    public function tr_dt_pro_main()
    {
        return $this->hasMany(tr_dt_pro_main::class, 'cut_id', 'id');
    }

    public function tr_inc_fg_det()
    {
        return $this->hasMany(tr_inc_fg_det::class, 'cut_id', 'id');
    }

    public function tr_pro_main()
    {
        return $this->hasMany(tr_pro_main::class, 'cut_id', 'id');
    }

    public function tr_pro_pal_pr()
    {
        return $this->hasMany(tr_pro_pal_pr::class, 'cut_id', 'id');
    }

    public function tr_pro_pallet()
    {
        return $this->hasMany(tr_pro_pallet::class, 'cut_id', 'id');
    }
}
