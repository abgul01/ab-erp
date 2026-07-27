<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sls_so_detail extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'sls_so_detail';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'main_id',
        'item_id',
        'po_detail_code',
        'qty',
        'price',
        'pricelist_det_id',
        'tax_id',
        'pph_tax_id',
        'dpp',
        'ppn_value',
        'pph_value',
        'local_mat',
        'ppn',
        'pph',
        'due_date',
        'note',
        'qty_delivered',
    ];

    public function main()
    {
        return $this->belongsTo(sls_so_main::class, 'main_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function tax()
    {
        return $this->belongsTo(m_tax::class, 'tax_id', 'id');
    }

    public function pph_tax()
    {
        return $this->belongsTo(m_tax::class, 'pph_tax_id', 'id');
    }

    /** Null when the operator typed the price by hand instead of taking the pricelist. */
    public function pricelist_det()
    {
        return $this->belongsTo(m_pricelist_det::class, 'pricelist_det_id', 'id');
    }
}
