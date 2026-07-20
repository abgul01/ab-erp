<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_pal_item extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_pal_item';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'item_id'
    ];

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function detail()
    {
        return $this->hasMany(m_pal_item_det::class, 'id_prim', 'id');
    }
}
