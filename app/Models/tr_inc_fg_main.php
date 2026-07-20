<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_inc_fg_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_inc_fg_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'date',
        'user_id'
    ];

    public function detail()
    {
        return $this->hasMany(tr_inc_fg_det::class, 'main_id', 'id');
    }

    public function tr_inc_fg_det_udf()
    {
        return $this->hasMany(tr_inc_fg_det_udf::class, 'main_id', 'id');
    }
}
