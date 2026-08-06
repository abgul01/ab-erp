<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu baris risiko: mode kegagalan, akibat, sebab, kendali, dan RPN-nya. */
class npd_fmea_det extends Model
{
    protected $connection = 'mysql';

    protected $table = 'npd_fmea_det';

    protected $fillable = [
        'main_id', 'proc_id', 'item_function', 'failure_mode', 'effect',
        'severity', 'cause', 'occurrence', 'current_control', 'detection',
        'rpn', 'recommended_action', 'action_taken', 'resp_user_id', 'due_date', 'status',
    ];

    protected $casts = [
        'severity' => 'integer',
        'occurrence' => 'integer',
        'detection' => 'integer',
        'rpn' => 'integer',
        'due_date' => 'date:Y-m-d',
    ];

    public function proc()
    {
        return $this->belongsTo(m_process::class, 'proc_id', 'id');
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'resp_user_id', 'id');
    }
}
