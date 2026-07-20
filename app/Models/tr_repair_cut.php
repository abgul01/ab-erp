<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_repair_cut extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_repair_cut';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'main_id',
        'user_id',
        'serial_id',
        'qty'
    ];

}
