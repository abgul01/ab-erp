<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_dt_cut_detail extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_dt_cut_detail';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'main_id',
        'serial_item',
        'tools_id'
    ];

    public function main()
    {
        return $this->belongsTo(tr_dt_cut_main::class, 'main_id', 'id');
    }
}
