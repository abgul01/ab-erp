<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sls_return extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'sls_return';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'do_id',
        'reason'
    ];

    public function do()
    {
        return $this->belongsTo(sls_do_main::class, 'do_id', 'id');
    }
}
