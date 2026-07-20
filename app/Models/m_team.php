<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_team extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_team';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'descript'
    ];

}
