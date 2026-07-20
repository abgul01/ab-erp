<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class wh_out_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'wh_out_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'wo_id',
        'item_id',
        'user_id',
        'date',
        'cus_id',
        'shift_id'
    ];

    public function wo()
    {
        return $this->belongsTo(prd_wo_main::class, 'wo_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function cus()
    {
        return $this->belongsTo(m_contacts::class, 'cus_id', 'id');
    }

    public function shift()
    {
        return $this->belongsTo(m_shift::class, 'shift_id', 'id');
    }

    public function detail()
    {
        return $this->hasMany(wh_out_detail::class, 'id_prim', 'id');
    }

    public function wh_rem_main()
    {
        return $this->hasMany(wh_rem_main::class, 'out_id', 'id');
    }
}
