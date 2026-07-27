<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sls_do_detail extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'sls_do_detail';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;   // sls_do_detail has no created_at/updated_at

    protected $fillable = [
        'main_id',
        'so_detail_id',
        'item_id',
        'qty',
        'fg_code',
    ];

    public function main()
    {
        return $this->belongsTo(sls_do_main::class, 'main_id', 'id');
    }

    public function soDetail()
    {
        return $this->belongsTo(sls_so_detail::class, 'so_detail_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
