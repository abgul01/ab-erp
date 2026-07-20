<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prd_cut_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prd_cut_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'wip_id',
        'code',
        'item_id',
        'wo_id',
        'operator',
        'machine_id',
        'date',
        'shift'
    ];

    public function wip()
    {
        return $this->belongsTo(wip::class, 'wip_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function wo()
    {
        return $this->belongsTo(prd_wo_main::class, 'wo_id', 'id');
    }

    public function machine()
    {
        return $this->belongsTo(m_machine::class, 'machine_id', 'id');
    }

    public function detail()
    {
        return $this->hasMany(prd_cut_serial::class, 'main_id', 'id');
    }
}
