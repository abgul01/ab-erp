<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Packing list: how one delivery is divided into boxes. */
class sls_pack_main extends Model
{
    protected $connection = 'mysql';

    protected $table = 'sls_pack_main';

    protected $fillable = ['code', 'date', 'do_id', 'status', 'note', 'user_id'];

    protected $casts = ['date' => 'date:Y-m-d'];

    public function detail()
    {
        return $this->hasMany(sls_pack_det::class, 'main_id', 'id');
    }

    public function deliveryOrder()
    {
        return $this->belongsTo(sls_do_main::class, 'do_id', 'id');
    }
}
