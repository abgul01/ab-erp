<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_ab_cut_det extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_ab_cut_det';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'main_id',
        'serial_id',
        'qty'
    ];

    public function main()
    {
        return $this->belongsTo(tr_ab_cut_main::class, 'main_id', 'id');
    }
}
