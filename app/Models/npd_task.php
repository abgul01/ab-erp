<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class npd_task extends Model
{
    protected $connection = 'mysql';

    protected $table = 'npd_task';

    protected $fillable = [
        'main_id', 'name', 'descrip', 'assigned_to', 'planned_start', 'planned_end',
        'actual_start', 'actual_end', 'progress_pct', 'predecessor_id', 'status',
    ];

    protected $casts = [
        'planned_start' => 'date:Y-m-d',
        'planned_end' => 'date:Y-m-d',
        'actual_start' => 'date:Y-m-d',
        'actual_end' => 'date:Y-m-d',
        'progress_pct' => 'integer',
    ];

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to', 'id');
    }

    /** Terlambat = lewat rencana selesai dan belum kelar. */
    public function isLate(): bool
    {
        return $this->status !== 'DONE'
            && $this->planned_end
            && $this->planned_end->isBefore(now()->startOfDay());
    }
}
