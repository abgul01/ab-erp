<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Control Plan: apa yang diukur di lantai produksi, seberapa sering, dan tindakannya bila menyimpang. */
class npd_cp_main extends Model
{
    public const TYPES = ['PROTOTYPE', 'PRE_LAUNCH', 'PRODUCTION'];

    protected $connection = 'mysql';

    protected $table = 'npd_cp_main';

    protected $fillable = ['main_id', 'code', 'revision', 'cp_type', 'date', 'status', 'user_id'];

    protected $casts = ['date' => 'date:Y-m-d'];

    public function detail()
    {
        return $this->hasMany(npd_cp_det::class, 'main_id', 'id')->orderBy('seq');
    }

    public function project()
    {
        return $this->belongsTo(npd_project::class, 'main_id', 'id');
    }
}
