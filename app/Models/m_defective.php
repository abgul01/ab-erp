<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_defective extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_defective';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'type'
    ];

}
