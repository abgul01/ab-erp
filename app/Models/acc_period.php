<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class acc_period extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'acc_period';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;


    protected $fillable = [
        'period',
        'status',
    ];

}
