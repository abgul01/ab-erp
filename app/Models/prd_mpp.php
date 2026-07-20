<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prd_mpp extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prd_mpp';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'period',
        'item_id',
        'plan_qty',
        'status',
    ];

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
