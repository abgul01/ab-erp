<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class wh_rem_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'wh_rem_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'out_id',
        'code',
        'date',
        'user_id',
        'item_id',
        'shift_id'
    ];

    public function out()
    {
        return $this->belongsTo(wh_out_main::class, 'out_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function shift()
    {
        return $this->belongsTo(m_shift::class, 'shift_id', 'id');
    }

    public function detail()
    {
        return $this->hasMany(wh_rem_detail::class, 'id_prim', 'id');
    }
}
