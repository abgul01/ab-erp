<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Permintaan penawaran dari pelanggan — hulu dari seluruh proyek NPD. */
class npd_rfq extends Model
{
    protected $connection = 'mysql';

    protected $table = 'npd_rfq';

    protected $fillable = [
        'main_id', 'cus_id', 'code', 'date', 'part_name', 'drawing_ref',
        'qty', 'target_price', 'due_date', 'status', 'note', 'user_id',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'due_date' => 'date:Y-m-d',
        'qty' => 'integer',
        'target_price' => 'float',
    ];

    public function cus()
    {
        return $this->belongsTo(m_contacts::class, 'cus_id', 'id');
    }

    public function project()
    {
        return $this->belongsTo(npd_project::class, 'main_id', 'id');
    }

    public function feasibility()
    {
        return $this->hasOne(npd_feasibility::class, 'rfq_id', 'id');
    }
}
