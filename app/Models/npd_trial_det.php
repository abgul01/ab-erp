<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu hasil ukur: satu parameter pada satu benda uji. */
class npd_trial_det extends Model
{
    protected $connection = 'mysql';

    protected $table = 'npd_trial_det';

    public $timestamps = false;

    protected $fillable = [
        'main_id', 'param_id', 'sample_no', 'nominal', 'min_value', 'max_value',
        'measured', 'judgement', 'instrument', 'inspector_id', 'note',
    ];

    protected $casts = [
        'sample_no' => 'integer',
        'nominal' => 'float',
        'min_value' => 'float',
        'max_value' => 'float',
        'measured' => 'float',
    ];

    public function param()
    {
        return $this->belongsTo(m_inspection_param::class, 'param_id', 'id');
    }

    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspector_id', 'id');
    }
}
