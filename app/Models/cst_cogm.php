<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class cst_cogm extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'cst_cogm';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'material_cost',
        'foh_cost',
        'scrap_recovery',
        'unit_cost'
    ];

}
