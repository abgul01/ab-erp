<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class wh_out_detail extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'wh_out_detail';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'id_prim',
        'serial_id',
        'qty',
        'pm',
        'length_serial',
        'length_used',
        'length_rem',
        'rem_data',
        'weight_used'
    ];

    public function main()
    {
        return $this->belongsTo(wh_out_main::class, 'id_prim', 'id');
    }
}
