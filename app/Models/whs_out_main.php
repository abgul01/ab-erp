<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pengeluaran barang WHS ke pemakai, mesin, atau aset. */
class whs_out_main extends Model
{
    protected $connection = 'mysql';

    protected $table = 'whs_out_main';

    protected $fillable = [
        'code', 'date', 'dept', 'receiver', 'cost_center', 'note',
        'status', 'posted_at', 'user_id',
    ];

    protected $casts = ['date' => 'date:Y-m-d', 'posted_at' => 'datetime'];

    public function detail()
    {
        return $this->hasMany(whs_out_det::class, 'main_id', 'id');
    }
}
