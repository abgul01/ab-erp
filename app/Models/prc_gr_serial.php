<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prc_gr_serial extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prc_gr_serial';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'det_id',
        'serial_id',
        'millsheet',
        'qty',
        'length',
        'weight',
        'status',
        'ng_reason',
    ];

    public function detail()
    {
        return $this->belongsTo(prc_gr_detail::class, 'det_id', 'id');
    }
}
