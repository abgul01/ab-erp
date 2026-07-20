<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_asset_categ extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_asset_categ';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'useful_life',
        'depr_method'
    ];

}
