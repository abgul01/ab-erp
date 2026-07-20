<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class wh_gen_out_det extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'wh_gen_out_det';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'item_id',
        'cost_center'
    ];

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
