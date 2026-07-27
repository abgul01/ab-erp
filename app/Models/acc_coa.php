<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class acc_coa extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'acc_coa';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;


    protected $fillable = [
        'code',
        'name',
        'acc_group',
        'parent_id',
        'postable',
    ];

}
