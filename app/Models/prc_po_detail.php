<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prc_po_detail extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prc_po_detail';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'main_id',
        'pr_detail_id',
        'item_id',
        'qty',
        'uom_id',
        'price',
        'price_kg',
        'tax_id',
        'est_weight_unit',
        'est_length_unit',
        'est_weight',
        'est_length',
        'due_date',
        'qty_received',
    ];

    public function tax()
    {
        return $this->belongsTo(m_tax::class, 'tax_id', 'id');
    }

    public function main()
    {
        return $this->belongsTo(prc_po_main::class, 'main_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function uom()
    {
        return $this->belongsTo(m_uom::class, 'uom_id', 'id');
    }

    public function prDetail()
    {
        return $this->belongsTo(prc_pr_detail::class, 'pr_detail_id', 'id');
    }

    public function schedules()
    {
        return $this->hasMany(prc_po_schedule::class, 'po_detail_id', 'id');
    }
}
