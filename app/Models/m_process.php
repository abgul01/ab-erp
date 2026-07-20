<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_process extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_process';
    protected $primaryKey = 'id';
    public $incrementing = true;
    const UPDATED_AT = 'updated';

    protected $fillable = [
        'code',
        'name_p',
        'descript',
        'active',
        'updated'
    ];

    public function prd_crp()
    {
        return $this->hasMany(prd_crp::class, 'process_id', 'id');
    }

    public function tr_ab_cut_main()
    {
        return $this->hasMany(tr_ab_cut_main::class, 'process_id', 'id');
    }

    public function tr_ab_pro()
    {
        return $this->hasMany(tr_ab_pro::class, 'process_id', 'id');
    }

    public function tr_cut_main()
    {
        return $this->hasMany(tr_cut_main::class, 'process_id', 'id');
    }

    public function tr_cut_pal_pr()
    {
        return $this->hasMany(tr_cut_pal_pr::class, 'process_id', 'id');
    }

    public function tr_pro_main()
    {
        return $this->hasMany(tr_pro_main::class, 'process_id', 'id');
    }

    public function tr_pro_pal_pr()
    {
        return $this->hasMany(tr_pro_pal_pr::class, 'process_id', 'id');
    }
}
