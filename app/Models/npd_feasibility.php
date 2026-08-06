<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Studi kelayakan atas sebuah RFQ: sanggup atau tidak, dan dengan syarat apa. */
class npd_feasibility extends Model
{
    protected $connection = 'mysql';

    protected $table = 'npd_feasibility';

    protected $fillable = [
        'rfq_id', 'main_id', 'tech_ok', 'capacity_ok', 'cost_ok',
        'material_avail', 'conclusion', 'note', 'user_id', 'evaluated_at',
    ];

    protected $casts = [
        'tech_ok' => 'boolean',
        'capacity_ok' => 'boolean',
        'cost_ok' => 'boolean',
        'evaluated_at' => 'datetime',
    ];

    public function rfq()
    {
        return $this->belongsTo(npd_rfq::class, 'rfq_id', 'id');
    }
}
