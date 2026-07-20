<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class wip extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'wip';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'user_id',
        'no_dp',
        'item_id',
        'date'
    ];

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function prd_cut_main()
    {
        return $this->hasMany(prd_cut_main::class, 'wip_id', 'id');
    }

    public function tr_ab_cut_main()
    {
        return $this->hasMany(tr_ab_cut_main::class, 'wip_id', 'id');
    }

    public function tr_ab_pro()
    {
        return $this->hasMany(tr_ab_pro::class, 'wip_id', 'id');
    }

    public function tr_cut_main()
    {
        return $this->hasMany(tr_cut_main::class, 'wip_id', 'id');
    }

    public function tr_inc_fg_det()
    {
        return $this->hasMany(tr_inc_fg_det::class, 'wip_id', 'id');
    }

    public function tr_pro_main()
    {
        return $this->hasMany(tr_pro_main::class, 'wip_id', 'id');
    }
}
