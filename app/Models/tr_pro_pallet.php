<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_pro_pallet extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_pro_pallet';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'detail_id',
        'cut_id',
        'pallet_code',
        'qty_half',
        'qty_full',
        'finish'
    ];

    public function cut()
    {
        return $this->belongsTo(tr_cut_main::class, 'cut_id', 'id');
    }
}
