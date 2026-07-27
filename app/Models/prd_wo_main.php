<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prd_wo_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prd_wo_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'date',
        'customer_id',
        'so_id',
        'fg_id',
        'process_main_id',
        'mps_id',
        'user_id',
        'qty',
        'status',
        'no_cut',
        'for_pm'
    ];

    public function customer()
    {
        return $this->belongsTo(m_contacts::class, 'customer_id', 'id');
    }

    public function fg()
    {
        return $this->belongsTo(m_item::class, 'fg_id', 'id');
    }

    /** The routing template chosen for this WO (from the item's ranked list). */
    public function processMain()
    {
        return $this->belongsTo(m_process_main::class, 'process_main_id', 'id');
    }

    public function mps()
    {
        return $this->belongsTo(prd_mps::class, 'mps_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function prd_cut_main()
    {
        return $this->hasMany(prd_cut_main::class, 'wo_id', 'id');
    }

    public function prd_wip()
    {
        return $this->hasMany(prd_wip::class, 'wo_id', 'id');
    }

    public function detailRm()
    {
        return $this->hasMany(prd_wo_detail_rm::class, 'main_id', 'id');
    }

    public function detailPm()
    {
        return $this->hasMany(prd_wo_detail_pm::class, 'main_id', 'id');
    }

    // Legacy aliases
    public function detail()
    {
        return $this->hasMany(prd_wo_detail_pm::class, 'main_id', 'id');
    }

    public function prd_wo_detail_rm()
    {
        return $this->hasMany(prd_wo_detail_rm::class, 'main_id', 'id');
    }

    public function wh_out_main()
    {
        return $this->hasMany(wh_out_main::class, 'wo_id', 'id');
    }

    public function wh_rem_detail()
    {
        return $this->hasMany(wh_rem_detail::class, 'wo_id', 'id');
    }
}
