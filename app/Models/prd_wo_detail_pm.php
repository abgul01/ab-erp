<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prd_wo_detail_pm extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prd_wo_detail_pm';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'main_id',
        'code_tr',
        'pm_id',
        'note'
    ];

    public function main()
    {
        return $this->belongsTo(prd_wo_main::class, 'main_id', 'id');
    }

    public function pm()
    {
        return $this->belongsTo(m_item::class, 'pm_id', 'id');
    }

    public function serials()
    {
        return $this->hasMany(prd_wo_serial_pm::class, 'detail_id', 'id');
    }
}
