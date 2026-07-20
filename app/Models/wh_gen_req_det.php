<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class wh_gen_req_det extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'wh_gen_req_det';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'qty',
        'asset_id'
    ];

    public function asset()
    {
        return $this->belongsTo(ast_main::class, 'asset_id', 'id');
    }
}
