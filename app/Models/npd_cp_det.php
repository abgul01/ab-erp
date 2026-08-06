<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu karakteristik yang dikendalikan pada satu proses. */
class npd_cp_det extends Model
{
    protected $connection = 'mysql';

    protected $table = 'npd_cp_det';

    protected $fillable = [
        'main_id', 'seq', 'proc_id', 'param_id', 'nominal', 'min_value', 'max_value',
        'method', 'sample_size', 'frequency', 'control_method', 'reaction_plan',
        'fmea_det_id', 'to_item_inspection',
    ];

    protected $casts = [
        'seq' => 'integer',
        'nominal' => 'float',
        'min_value' => 'float',
        'max_value' => 'float',
        'sample_size' => 'integer',
        'to_item_inspection' => 'boolean',
    ];

    public function proc()
    {
        return $this->belongsTo(m_process::class, 'proc_id', 'id');
    }

    public function param()
    {
        return $this->belongsTo(m_inspection_param::class, 'param_id', 'id');
    }

    public function fmeaLine()
    {
        return $this->belongsTo(npd_fmea_det::class, 'fmea_det_id', 'id');
    }
}
