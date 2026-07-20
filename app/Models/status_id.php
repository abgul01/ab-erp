<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class status_id extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'status_id';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'status'
    ];

}
