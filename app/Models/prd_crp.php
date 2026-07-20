<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prd_crp extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prd_crp';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'process_id',
        'load_hours'
    ];

    public function process()
    {
        return $this->belongsTo(m_process::class, 'process_id', 'id');
    }
}
