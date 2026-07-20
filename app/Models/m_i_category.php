<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_i_category extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_i_category';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'name_c'
    ];

}
