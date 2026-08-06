<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An operational warning waiting for someone to act on it. */
class sys_alert extends Model
{
    protected $connection = 'mysql';

    protected $table = 'sys_alert';

    protected $fillable = [
        'type', 'severity', 'ref_type', 'ref_id',
        'title', 'message', 'value', 'threshold',
        'resolved_at', 'resolved_by',
    ];

    protected $casts = [
        'value' => 'float',
        'threshold' => 'float',
        'resolved_at' => 'datetime',
    ];

    public function scopeOpen($q)
    {
        return $q->whereNull('resolved_at');
    }
}
