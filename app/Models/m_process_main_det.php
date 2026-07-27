<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_process_main_det extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_process_main_det';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'main_id',
        'proc_id',
        'sequence',
    ];

    public function main()
    {
        return $this->belongsTo(m_process_main::class, 'main_id', 'id');
    }

    public function process()
    {
        return $this->belongsTo(m_process::class, 'proc_id', 'id');
    }
}
