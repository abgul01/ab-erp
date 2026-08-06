<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prd_wo_serial_rm extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'prd_wo_serial_rm';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $fillable = [
        'detail_id',
        'serial_id',
        'length_asal',
        'length_book',
        'qty_per_serial',
        'length_rem',
        'qty_serial',
        'scrap',
        'note',
        'version',
    ];

    public function detail()
    {
        return $this->belongsTo(prd_wo_detail_rm::class, 'detail_id', 'id');
    }
}
