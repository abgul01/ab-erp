<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_i_pm extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_i_pm';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'item_id',
        'active'
    ];

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
