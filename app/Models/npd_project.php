<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Proyek pengembangan part baru, mengikuti 5 fase APQP. */
class npd_project extends Model
{
    public const TYPES = ['NEW', 'MODIFICATION', 'DERIVATIVE'];

    protected $connection = 'mysql';

    protected $table = 'npd_project';

    protected $fillable = [
        'code', 'name', 'cus_id', 'parent_item_id', 'part_name', 'drawing_no',
        'project_type', 'current_phase_no', 'status', 'target_sop', 'pm_user_id',
        'priority', 'item_id', 'bom_id', 'process_main_id', 'handover_date',
        'note', 'user_id',
    ];

    protected $casts = [
        'target_sop' => 'date:Y-m-d',
        'handover_date' => 'date:Y-m-d',
        'current_phase_no' => 'integer',
    ];

    public function phases()
    {
        return $this->hasMany(npd_project_phase::class, 'main_id', 'id')->orderBy('phase_no');
    }

    public function milestones()
    {
        return $this->hasMany(npd_milestone::class, 'main_id', 'id')->orderBy('planned_date');
    }

    public function members()
    {
        return $this->hasMany(npd_member::class, 'main_id', 'id');
    }

    public function docs()
    {
        return $this->hasMany(npd_doc::class, 'main_id', 'id')->orderByDesc('id');
    }

    public function cus()
    {
        return $this->belongsTo(m_contacts::class, 'cus_id', 'id');
    }

    public function pm()
    {
        return $this->belongsTo(User::class, 'pm_user_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    /** Fase yang sedang dikerjakan. */
    public function currentPhase()
    {
        return $this->hasOne(npd_project_phase::class, 'main_id', 'id')
            ->whereColumn('phase_no', 'npd_project.current_phase_no');
    }
}
