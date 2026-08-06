<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Trial produksi part baru — selalu dieksekusi lewat Work Order-nya sendiri. */
class npd_trial_main extends Model
{
    public const TYPES = ['PROTOTYPE', 'PILOT', 'MASS_TRIAL'];

    protected $connection = 'mysql';

    protected $table = 'npd_trial_main';

    protected $fillable = [
        'main_id', 'phase_id', 'wo_id', 'code', 'trial_type', 'date', 'machine_id',
        'planned_qty', 'produced_qty', 'ok_qty', 'ng_qty', 'conclusion', 'status', 'user_id',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'planned_qty' => 'integer',
        'produced_qty' => 'integer',
        'ok_qty' => 'integer',
        'ng_qty' => 'integer',
    ];

    public function detail()
    {
        return $this->hasMany(npd_trial_det::class, 'main_id', 'id')
            ->orderBy('param_id')->orderBy('sample_no');
    }

    public function project()
    {
        return $this->belongsTo(npd_project::class, 'main_id', 'id');
    }

    public function wo()
    {
        return $this->belongsTo(prd_wo_main::class, 'wo_id', 'id');
    }

    public function machine()
    {
        return $this->belongsTo(m_machine::class, 'machine_id', 'id');
    }

    /** Berapa persen hasil ukurnya masuk spesifikasi. */
    public function passRate(): ?float
    {
        $total = $this->detail->count();

        return $total === 0 ? null : round($this->detail->where('judgement', 'OK')->count() / $total * 100, 1);
    }
}
