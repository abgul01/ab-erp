<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class acc_journal_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'acc_journal_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'date',
        'period',
        'jrn_type',
        'ref_type',
        'ref_id',
        'descrip',
        'status',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function detail()
    {
        return $this->hasMany(acc_journal_det::class, 'main_id', 'id');
    }
}
