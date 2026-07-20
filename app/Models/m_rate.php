<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_rate extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_rate';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'rate_type',
        'rate'
    ];

}
