<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class cst_rate extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'cst_rate';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'rate_type',
        'rate_per_hour'
    ];

}
