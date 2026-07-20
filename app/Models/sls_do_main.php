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
        'date',
        'status'
    ];

    public function sls_return()
    {
        return $this->hasMany(sls_return::class, 'do_id', 'id');
    }
}
