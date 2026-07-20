<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_uom extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_uom';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'name',
        'uom_type',
        'active'
    ];

}
