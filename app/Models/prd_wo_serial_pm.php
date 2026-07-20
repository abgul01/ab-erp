<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prd_wo_serial_pm extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prd_wo_serial_pm';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'detail_id',
        'serial_id',
        'qty',
        'note'
    ];

    public function detail()
    {
        return $this->belongsTo(prd_wo_detail_pm::class, 'detail_id', 'id');
    }
}
