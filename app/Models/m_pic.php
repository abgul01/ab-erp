<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_pic extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_pic';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'pic_id',
        'type',
        'active'
    ];

}
