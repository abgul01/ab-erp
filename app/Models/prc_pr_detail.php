<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prc_pr_detail extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prc_pr_detail';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'main_id',
        'item_id',
        'qty',
        'uom_id',
        'need_date',
        'wo_id',
        'note',
    ];

    public function main()
    {
        return $this->belongsTo(prc_pr_main::class, 'main_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function uom()
    {
        return $this->belongsTo(m_uom::class, 'uom_id', 'id');
    }
}
