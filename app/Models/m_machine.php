<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_machine extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_machine';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'name',
        'model',
        'categ',
        'maker_id',
        'min_d',
        'max_d',
        'func_id',
        'serial',
        'y_made',
        'etd',
        'pic_jp_id',
        'pic_local_id',
        'book_y_local',
        'asset_id',
        'deps_m',
        'deps_exp',
        'kwh',
        'active'
    ];

    public function prd_cut_main()
    {
        return $this->hasMany(prd_cut_main::class, 'machine_id', 'id');
    }

    public function tr_ab_cut_main()
    {
        return $this->hasMany(tr_ab_cut_main::class, 'machine_id', 'id');
    }

    public function tr_ab_pro()
    {
        return $this->hasMany(tr_ab_pro::class, 'machine_id', 'id');
    }

    public function tr_cut_detail()
    {
        return $this->hasMany(tr_cut_detail::class, 'machine_id', 'id');
    }

    public function tr_dt_cut_main()
    {
        return $this->hasMany(tr_dt_cut_main::class, 'machine_id', 'id');
    }

    public function tr_dt_pro_main()
    {
        return $this->hasMany(tr_dt_pro_main::class, 'machine_id', 'id');
    }

    public function tr_pro_detail()
    {
        return $this->hasMany(tr_pro_detail::class, 'machine_id', 'id');
    }
}
