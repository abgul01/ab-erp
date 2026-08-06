<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only record of every scrap ⇄ usable call on a booked serial. */
class prd_scrap_decision extends Model
{
    protected $connection = 'mysql';

    protected $table = 'prd_scrap_decisions';

    public $timestamps = false;

    protected $fillable = [
        'serial_id', 'wo_serial_rm_id', 'wo_id', 'item_id',
        'length_rem', 'min_bom_length', 'decision', 'reason',
        'auto_flag', 'decided_by', 'created_at',
    ];

    protected $casts = [
        'auto_flag' => 'boolean',
        'length_rem' => 'float',
        'min_bom_length' => 'float',
        'created_at' => 'datetime',
    ];

    public function serial()
    {
        return $this->belongsTo(prd_wo_serial_rm::class, 'wo_serial_rm_id', 'id');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by', 'id');
    }
}
