<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Preliminary BOM proyek NPD — belum mengikat produksi sampai handover. */
class npd_bom_main extends Model
{
    protected $connection = 'mysql';

    protected $table = 'npd_bom_main';

    protected $fillable = [
        'main_id', 'version', 'status', 'effective_date', 'note', 'approved_by', 'user_id',
    ];

    protected $casts = ['effective_date' => 'date:Y-m-d'];

    public function detail()
    {
        return $this->hasMany(npd_bom_det::class, 'main_id', 'id');
    }

    public function project()
    {
        return $this->belongsTo(npd_project::class, 'main_id', 'id');
    }
}
