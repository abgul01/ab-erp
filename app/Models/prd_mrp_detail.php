<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prd_mrp_detail extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prd_mrp_detail';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'period',
        'open_po',
        'net_req_kg',
        'suggestion'
    ];

}
