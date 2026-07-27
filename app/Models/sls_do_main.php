<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sls_do_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'sls_do_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'date',
        'so_id',
        'user_id',
        'status',
    ];

    public function detail()
    {
        return $this->hasMany(sls_do_detail::class, 'main_id', 'id');
    }

    public function so()
    {
        return $this->belongsTo(sls_so_main::class, 'so_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function sls_return()
    {
        return $this->hasMany(sls_return::class, 'do_id', 'id');
    }
}
