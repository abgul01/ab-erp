<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class wh_rem_detail extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'wh_rem_detail';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'id_prim',
        'serial_id',
        'wo_id',
        'length',
        'rem_count',
        'rack_id',
        'weight'
    ];

    public function main()
    {
        return $this->belongsTo(wh_rem_main::class, 'id_prim', 'id');
    }

    public function wo()
    {
        return $this->belongsTo(prd_wo_main::class, 'wo_id', 'id');
    }

    public function rack()
    {
        return $this->belongsTo(m_rack::class, 'rack_id', 'id');
    }
}
