<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class qc_incoming_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'qc_incoming_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'gr_id',
        'inspector_id'
    ];

    public function gr()
    {
        return $this->belongsTo(prc_gr_main::class, 'gr_id', 'id');
    }
}
