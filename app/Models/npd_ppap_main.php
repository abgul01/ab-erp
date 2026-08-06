<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu paket PPAP yang diajukan ke pelanggan. */
class npd_ppap_main extends Model
{
    protected $connection = 'mysql';

    protected $table = 'npd_ppap_main';

    protected $fillable = [
        'main_id', 'code', 'ppap_level', 'psw_no', 'submission_date',
        'status', 'approval_date', 'customer_pic', 'note', 'user_id',
    ];

    protected $casts = [
        'ppap_level' => 'integer',
        'submission_date' => 'date:Y-m-d',
        'approval_date' => 'date:Y-m-d',
    ];

    public function detail()
    {
        return $this->hasMany(npd_ppap_det::class, 'main_id', 'id');
    }

    public function project()
    {
        return $this->belongsTo(npd_project::class, 'main_id', 'id');
    }

    /** Elemen yang wajib untuk level ini tetapi belum beres. */
    public function outstanding()
    {
        return $this->detail
            ->filter(fn ($d) => $d->std?->requiredForLevel($this->ppap_level) && $d->status === 'OPEN');
    }
}
