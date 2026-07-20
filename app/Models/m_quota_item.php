<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_quota_item extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_quota_item';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'quota_id',
        'item_id',
    ];

    public function quota()
    {
        return $this->belongsTo(m_quota::class, 'quota_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
