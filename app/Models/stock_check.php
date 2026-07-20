<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stock_check extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'stock_check';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'date',
        'item_id',
        'rack_id',
        'length',
        'match',
        'qty_data',
        'qty_actual'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function rack()
    {
        return $this->belongsTo(m_rack::class, 'rack_id', 'id');
    }
}
