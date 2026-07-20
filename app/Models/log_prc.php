<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class log_prc extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'log_prc';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code_tr',
        'date',
        'user_id',
        'ip_user',
        'hostname',
        'action'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
