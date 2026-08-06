<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Shipping order: one vehicle, one trip, one or more deliveries. */
class sls_ship_main extends Model
{
    protected $connection = 'mysql';

    protected $table = 'sls_ship_main';

    protected $fillable = [
        'code', 'date', 'carrier_id', 'vehicle_no', 'driver', 'driver_phone',
        'destination', 'plan_depart', 'departed_at', 'arrived_at', 'status', 'note', 'user_id',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'plan_depart' => 'datetime',
        'departed_at' => 'datetime',
        'arrived_at' => 'datetime',
    ];

    public function detail()
    {
        return $this->hasMany(sls_ship_det::class, 'main_id', 'id');
    }

    public function carrier()
    {
        return $this->belongsTo(m_contacts::class, 'carrier_id', 'id');
    }
}
