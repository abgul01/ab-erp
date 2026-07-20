<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_bom_pro_det extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_bom_pro_det';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'id_prim',
        'proc_id',
        'sequence'
    ];

    public function main()
    {
        return $this->belongsTo(m_bom_pro::class, 'id_prim', 'id');
    }

    public function process()
    {
        return $this->belongsTo(m_process::class, 'proc_id', 'id');
    }
}
