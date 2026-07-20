<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_cut_pal_pr extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_cut_pal_pr';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'cut_id',
        'qty',
        'process_id'
    ];

    public function cut()
    {
        return $this->belongsTo(tr_cut_main::class, 'cut_id', 'id');
    }

    public function process()
    {
        return $this->belongsTo(m_process::class, 'process_id', 'id');
    }
}
