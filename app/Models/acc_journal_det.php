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
    public $timestamps = false;


    protected $fillable = [
        'main_id',
        'coa_id',
        'debit',
        'credit',
        'memo',
    ];

    public function coa()
    {
        return $this->belongsTo(acc_coa::class, 'coa_id', 'id');
    }
}
