<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_dt_pro_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_dt_pro_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'pro_id',
        'code',
        'user_id',
        'machine_id',
        'det_pro_id',
        'cut_id',
        'cat_id',
        'note',
        'start_time',
        'end_time'
    ];

    public function pro()
    {
        return $this->belongsTo(tr_pro_main::class, 'pro_id', 'id');
    }

    public function machine()
    {
        return $this->belongsTo(m_machine::class, 'machine_id', 'id');
    }

    public function det_pro()
    {
        return $this->belongsTo(tr_pro_detail::class, 'det_pro_id', 'id');
    }

    public function cut()
    {
        return $this->belongsTo(tr_cut_main::class, 'cut_id', 'id');
    }

    public function cat()
    {
        return $this->belongsTo(tr_dt_category::class, 'cat_id', 'id');
    }

    public function detail()
    {
        return $this->hasMany(tr_dt_pro_detail::class, 'main_id', 'id');
    }
}
