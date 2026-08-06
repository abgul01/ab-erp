<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu baris rincian biaya: material, upah, overhead pabrik, tooling, atau lainnya. */
class npd_cost_det extends Model
{
    protected $connection = 'mysql';

    protected $table = 'npd_cost_det';

    public $timestamps = false;

    protected $fillable = [
        'main_id', 'cost_type', 'proc_id', 'item_id', 'descrip',
        'qty', 'cycle_sec', 'rate', 'amount', 'note',
    ];

    protected $casts = [
        'qty' => 'float', 'cycle_sec' => 'float', 'rate' => 'float', 'amount' => 'float',
    ];

    public function proc()
    {
        return $this->belongsTo(m_process::class, 'proc_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
