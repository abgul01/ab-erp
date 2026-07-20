<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_item_customer extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_item_customer';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'item_id',
        'cus_id',
        'priority',
        'active'
    ];

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function cus()
    {
        return $this->belongsTo(m_contacts::class, 'cus_id', 'id');
    }
}
