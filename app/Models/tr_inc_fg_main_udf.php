<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_inc_fg_main_udf extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_inc_fg_main_udf';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'user_id',
        'date'
    ];

}
