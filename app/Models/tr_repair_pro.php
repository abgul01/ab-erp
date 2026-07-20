<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_repair_pro extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_repair_pro';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'main_id',
        'pallet_code',
        'qty'
    ];

}
