<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sub_dn_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'sub_dn_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'po_id',
        'status'
    ];

    public function po()
    {
        return $this->belongsTo(prc_po_main::class, 'po_id', 'id');
    }
}
