<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_ab_cut_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_ab_cut_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'wip_id',
        'user_id',
        'cut_id',
        'det_cut_id',
        'process_id',
        'date',
        'machine_id',
        'item_id',
        'note'
    ];

    public function wip()
    {
        return $this->belongsTo(wip::class, 'wip_id', 'id');
    }

    public function cut()
    {
        return $this->belongsTo(tr_cut_main::class, 'cut_id', 'id');
    }

    public function det_cut()
    {
        return $this->belongsTo(tr_cut_detail::class, 'det_cut_id', 'id');
    }

    public function process()
    {
        return $this->belongsTo(m_process::class, 'process_id', 'id');
    }

    public function machine()
    {
        return $this->belongsTo(m_machine::class, 'machine_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function detail()
    {
        return $this->hasMany(tr_ab_cut_det::class, 'main_id', 'id');
    }
}
