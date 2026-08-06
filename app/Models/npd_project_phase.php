<?php

namespace App\Models;

use App\Support\HasApproval;
use App\Support\NpdService;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu fase pada satu proyek — dan inilah dokumen yang disetujui saat gate.
 *
 * Memakai mesin approval umum, jadi keputusan gate muncul di inbox approval yang
 * sama dengan PO, MPS, dan ECN; approver tidak perlu layar tersendiri.
 */
class npd_project_phase extends Model
{
    use HasApproval;

    protected $connection = 'mysql';

    protected $table = 'npd_project_phase';

    protected $fillable = [
        'main_id', 'phase_id', 'phase_no', 'planned_start', 'planned_end',
        'actual_start', 'actual_end', 'status', 'pic_user_id',
    ];

    protected $casts = [
        'planned_start' => 'date:Y-m-d',
        'planned_end' => 'date:Y-m-d',
        'actual_start' => 'date:Y-m-d',
        'actual_end' => 'date:Y-m-d',
        'phase_no' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(npd_project::class, 'main_id', 'id');
    }

    public function phase()
    {
        return $this->belongsTo(npd_phase::class, 'phase_id', 'id');
    }

    public function tasks()
    {
        return $this->hasMany(npd_task::class, 'main_id', 'id');
    }

    public function deliverables()
    {
        return $this->hasMany(npd_deliverable::class, 'main_id', 'id');
    }

    /**
     * Gate yang disetujui penuh menutup fase ini dan membuka fase berikutnya.
     * Pemindahan proyek dilakukan NpdService, bukan di sini, supaya seluruh
     * aturan peralihan fase berada di satu tempat.
     */
    public function onFullyApproved(): void
    {
        $this->update(['status' => 'APPROVED', 'actual_end' => now()->toDateString()]);

        app(NpdService::class)->advanceAfterGate($this);
    }

    public function onRejected(): void
    {
        $this->update(['status' => 'REJECTED']);
    }
}
