<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class wh_inc_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'wh_inc_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'user_id',
        'gr_id',
        'date',
        'shift_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function gr()
    {
        return $this->belongsTo(prc_gr_main::class, 'gr_id', 'id');
    }

    public function shift()
    {
        return $this->belongsTo(m_shift::class, 'shift_id', 'id');
    }

    public function detail()
    {
        return $this->hasMany(wh_inc_detail::class, 'id_prim', 'id');
    }
}
