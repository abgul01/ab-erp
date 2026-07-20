<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sls_forecast extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'sls_forecast';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'cus_id',
        'item_id',
        'period',
        'version',
        'qty',
    ];

    public function cus()
    {
        return $this->belongsTo(m_contacts::class, 'cus_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
