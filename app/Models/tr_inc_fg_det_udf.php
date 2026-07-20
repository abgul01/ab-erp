<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_inc_fg_det_udf extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_inc_fg_det_udf';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'main_id',
        'pal_pro_code',
        'item_id',
        'no_lot',
        'qty'
    ];

    public function main()
    {
        return $this->belongsTo(tr_inc_fg_main::class, 'main_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
