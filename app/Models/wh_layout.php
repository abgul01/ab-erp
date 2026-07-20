<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class wh_layout extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'wh_layout';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'rack_id',
        'x_position',
        'y_position',
        'width',
        'height',
        'rotation',
        'type',
        'label'
    ];

    public function rack()
    {
        return $this->belongsTo(m_rack::class, 'rack_id', 'id');
    }
}
