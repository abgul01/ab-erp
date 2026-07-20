<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class wh_gen_out_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'wh_gen_out_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'req_id',
        'status'
    ];

    public function req()
    {
        return $this->belongsTo(wh_gen_req_main::class, 'req_id', 'id');
    }
}
