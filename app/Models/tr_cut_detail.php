<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_cut_detail extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_cut_detail';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'main_id',
        'machine_id',
        'start_time',
        'end_time',
        'finish'
    ];

    public function main()
    {
        return $this->belongsTo(tr_cut_main::class, 'main_id', 'id');
    }

    public function machine()
    {
        return $this->belongsTo(m_machine::class, 'machine_id', 'id');
    }

    public function tr_ab_cut_main()
    {
        return $this->hasMany(tr_ab_cut_main::class, 'det_cut_id', 'id');
    }

    public function tr_dt_cut_main()
    {
        return $this->hasMany(tr_dt_cut_main::class, 'det_cut_id', 'id');
    }
}
