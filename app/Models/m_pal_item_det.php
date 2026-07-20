<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_pal_item_det extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_pal_item_det';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'id_prim',
        'pal_id',
        'qty',
        'priority'
    ];

    public function main()
    {
        return $this->belongsTo(m_pal_item::class, 'id_prim', 'id');
    }

    public function pal()
    {
        return $this->belongsTo(m_pallet::class, 'pal_id', 'id');
    }
}
