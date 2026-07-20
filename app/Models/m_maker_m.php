<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_maker_m extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_maker_m';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'name',
        'address',
        'active'
    ];

}
