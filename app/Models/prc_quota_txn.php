<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prc_quota_txn extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prc_quota_txn';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'quota_id',
        'ref_type',
        'ref_id',
        'ton',
        'sign',
        'note',
        'user_id',
        'created_at',
    ];

    public function quota()
    {
        return $this->belongsTo(m_quota::class, 'quota_id', 'id');
    }
}
