<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sub_gr_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'sub_gr_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'date',
        'po_id',
        'dn_id',
        'ven_dn_no',
        'qty_ok',
        'qty_ng',
        'user_id',
        'status',
    ];

    public function po()
    {
        return $this->belongsTo(prc_po_main::class, 'po_id', 'id');
    }

    public function dn()
    {
        return $this->belongsTo(sub_dn_main::class, 'dn_id', 'id');
    }
}
