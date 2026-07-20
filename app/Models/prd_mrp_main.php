<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prd_mrp_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prd_mrp_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'status'
    ];

}
