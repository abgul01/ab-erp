<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_ng_pro extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_ng_pro';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'main_id',
        'user_id',
        'qty',
        'update_at'
    ];

}
