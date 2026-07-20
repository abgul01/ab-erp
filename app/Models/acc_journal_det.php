<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class acc_journal_det extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'acc_journal_det';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'debit',
        'memo'
    ];

}
