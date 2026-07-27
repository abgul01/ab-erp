<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sub_dn_detail extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'sub_dn_detail';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;   // sub_dn_detail has no timestamps

    protected $fillable = [
        'main_id',
        'wo_id',
        'item_id',
        'serial_id',
        'pallet_code',
        'qty',
    ];

    public function main()
    {
        return $this->belongsTo(sub_dn_main::class, 'main_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
