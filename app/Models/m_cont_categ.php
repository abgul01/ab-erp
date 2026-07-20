<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_cont_categ extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_cont_categ';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'name'
    ];

}
