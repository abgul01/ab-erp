<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class warehouse_layout_positions extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'warehouse_layout_positions';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'type',
        'element_id',
        'position_x',
        'position_y',
        'width',
        'height',
        'color',
        'rack_ref_id',
        'text_orientation',
        'active'
    ];

}
