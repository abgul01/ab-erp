<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_cut_serial extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_cut_serial';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'detail_id',
        'serial_id',
        'qty',
        'length_rem',
        'finish'
    ];

}
