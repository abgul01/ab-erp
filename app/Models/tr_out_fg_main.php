<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_out_fg_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_out_fg_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'code_do',
        'date',
        'user_id'
    ];

    public function detail()
    {
        return $this->hasMany(tr_out_fg_det::class, 'main_id', 'id');
    }
}
