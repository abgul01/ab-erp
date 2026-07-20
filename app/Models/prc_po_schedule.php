<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prc_po_schedule extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prc_po_schedule';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'po_detail_id',
        'plan_date',
        'qty',
        'confirmed_at',
    ];

    public function detail()
    {
        return $this->belongsTo(prc_po_detail::class, 'po_detail_id', 'id');
    }
}
