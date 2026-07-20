<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_dt_cut_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_dt_cut_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'user_id',
        'cut_id',
        'det_cut_id',
        'machine_id',
        'no_dp',
        'cat_id',
        'descriptions',
        'start_time',
        'end_time',
        'finish'
    ];

    public function cut()
    {
        return $this->belongsTo(tr_cut_main::class, 'cut_id', 'id');
    }

    public function det_cut()
    {
        return $this->belongsTo(tr_cut_detail::class, 'det_cut_id', 'id');
    }

    public function machine()
    {
        return $this->belongsTo(m_machine::class, 'machine_id', 'id');
    }

    public function cat()
    {
        return $this->belongsTo(tr_dt_category::class, 'cat_id', 'id');
    }

    public function detail()
    {
        return $this->hasMany(tr_dt_cut_detail::class, 'main_id', 'id');
    }
}
