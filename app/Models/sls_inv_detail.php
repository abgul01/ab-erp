<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sls_inv_detail extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'sls_inv_detail';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'item_id',
        'amount'
    ];

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
