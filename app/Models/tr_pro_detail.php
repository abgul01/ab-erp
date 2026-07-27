<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_pro_detail extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_pro_detail';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'main_id',
        'machine_id',
        'user_id',
        'qty_half',
        'qty_full',
        'finish',
        'start_time',
        'end_time'
    ];

    public function main()
    {
        return $this->belongsTo(tr_pro_main::class, 'main_id', 'id');
    }

    public function machine()
    {
        return $this->belongsTo(m_machine::class, 'machine_id', 'id');
    }

    public function tr_ab_pro()
    {
        return $this->hasMany(tr_ab_pro::class, 'det_pro_id', 'id');
    }

    public function tr_dt_pro_main()
    {
        return $this->hasMany(tr_dt_pro_main::class, 'det_pro_id', 'id');
    }
}
