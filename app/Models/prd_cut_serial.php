<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prd_cut_serial extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prd_cut_serial';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'main_id',
        'serial_id',
        'qty',
        'length_rem',
        'finish'
    ];

    public function main()
    {
        return $this->belongsTo(prd_cut_main::class, 'main_id', 'id');
    }
}
