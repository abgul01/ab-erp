<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** DFMEA (risiko desain) atau PFMEA (risiko proses) sebuah proyek NPD. */
class npd_fmea_main extends Model
{
    public const TYPES = ['DESIGN', 'PROCESS'];

    protected $connection = 'mysql';

    protected $table = 'npd_fmea_main';

    protected $fillable = [
        'main_id', 'fmea_type', 'code', 'revision', 'team', 'date',
        'rpn_threshold', 'status', 'user_id',
    ];

    protected $casts = ['date' => 'date:Y-m-d', 'rpn_threshold' => 'integer'];

    public function detail()
    {
        return $this->hasMany(npd_fmea_det::class, 'main_id', 'id')->orderByDesc('rpn');
    }

    public function project()
    {
        return $this->belongsTo(npd_project::class, 'main_id', 'id');
    }

    /** Risiko yang melewati ambang dan karena itu wajib punya tindakan. */
    public function aboveThreshold()
    {
        return $this->detail->where('rpn', '>=', $this->rpn_threshold);
    }
}
