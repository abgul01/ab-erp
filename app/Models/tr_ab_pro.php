<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_ab_pro extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_ab_pro';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'pro_id',
        'wip_id',
        'cut_id',
        'det_pro_id',
        'process_id',
        'pallet_code',
        'code',
        'date',
        'user_id',
        'item_id',
        'machine_id',
        'note'
    ];

    public function pro()
    {
        return $this->belongsTo(tr_pro_main::class, 'pro_id', 'id');
    }

    public function wip()
    {
        return $this->belongsTo(wip::class, 'wip_id', 'id');
    }

    public function cut()
    {
        return $this->belongsTo(tr_cut_main::class, 'cut_id', 'id');
    }

    public function det_pro()
    {
        return $this->belongsTo(tr_pro_detail::class, 'det_pro_id', 'id');
    }

    public function process()
    {
        return $this->belongsTo(m_process::class, 'process_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function machine()
    {
        return $this->belongsTo(m_machine::class, 'machine_id', 'id');
    }
}
