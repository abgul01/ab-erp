<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ast_depre extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'ast_depre';
    protected $primaryKey = 'id';
    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'ast_id',
        'period',
        'amount',
        'journal_id',
    ];

}
