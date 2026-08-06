<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One delivery riding on a shipping order. */
class sls_ship_det extends Model
{
    protected $connection = 'mysql';

    protected $table = 'sls_ship_det';

    public $timestamps = false;

    protected $fillable = ['main_id', 'do_id'];

    public function deliveryOrder()
    {
        return $this->belongsTo(sls_do_main::class, 'do_id', 'id');
    }
}
