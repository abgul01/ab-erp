<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class cst_cogm extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'cst_cogm';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'period',
        'wo_id',
        'material_cost',
        'labor_cost',
        'foh_cost',
        'subcont_cost',
        'scrap_recovery',
        'total',
        'unit_cost',
    ];

    public function wo()
    {
        return $this->belongsTo(prd_wo_main::class, 'wo_id', 'id');
    }
}
