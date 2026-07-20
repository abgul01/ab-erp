<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class wh_inc_detail extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'wh_inc_detail';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'id_prim',
        'serial_id',
        'length',
        'qty',
        'item_id',
        'rack_id'
    ];

    public function main()
    {
        return $this->belongsTo(wh_inc_main::class, 'id_prim', 'id');
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
