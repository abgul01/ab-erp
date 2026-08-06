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

    public $timestamps = false;   // prd_crp carries no created_at/updated_at

    protected $fillable = [
        'mrp_id',
        'basis',
        'process_id',
        'machine_id',
        'period',
        'load_hours',
        'capacity_hours',
    ];

    public function process()
    {
        return $this->belongsTo(m_process::class, 'process_id', 'id');
    }

    public function machine()
    {
        return $this->belongsTo(m_machine::class, 'machine_id', 'id');
    }
}
