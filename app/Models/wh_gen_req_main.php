<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class wh_gen_req_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'wh_gen_req_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'dept',
        'status'
    ];

    public function wh_gen_out_main()
    {
        return $this->hasMany(wh_gen_out_main::class, 'req_id', 'id');
    }
}
