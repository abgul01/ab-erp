<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_rack extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_rack';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'location',
        'descriptions',
        'height',
        'width',
        'area',
        'rem_rack',
        'active',
        'depth'
    ];

    public function stock_check()
    {
        return $this->hasMany(stock_check::class, 'rack_id', 'id');
    }

    public function sum_stock_rm()
    {
        return $this->hasMany(sum_stock_rm::class, 'rack_id', 'id');
    }

    public function wh_inc_detail()
    {
        return $this->hasMany(wh_inc_detail::class, 'rack_id', 'id');
    }

    public function wh_layout()
    {
        return $this->hasMany(wh_layout::class, 'rack_id', 'id');
    }

    public function wh_rem_detail()
    {
        return $this->hasMany(wh_rem_detail::class, 'rack_id', 'id');
    }
}
