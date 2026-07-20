<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prd_wo_detail_rm extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prd_wo_detail_rm';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'main_id',
        'rm_id',
        'note'
    ];

    public function main()
    {
        return $this->belongsTo(prd_wo_main::class, 'main_id', 'id');
    }

    public function rm()
    {
        return $this->belongsTo(m_item::class, 'rm_id', 'id');
    }

    public function serials()
    {
        return $this->hasMany(prd_wo_serial_rm::class, 'detail_id', 'id');
    }
}
