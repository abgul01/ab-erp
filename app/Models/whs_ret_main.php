<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pengembalian alat pinjaman ke gudang WHS. */
class whs_ret_main extends Model
{
    protected $connection = 'mysql';

    protected $table = 'whs_ret_main';

    protected $fillable = ['code', 'date', 'returner', 'note', 'status', 'posted_at', 'user_id'];

    protected $casts = ['date' => 'date:Y-m-d', 'posted_at' => 'datetime'];

    public function detail()
    {
        return $this->hasMany(whs_ret_det::class, 'main_id', 'id');
    }
}
