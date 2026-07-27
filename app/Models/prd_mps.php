<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prd_mps extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prd_mps';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'plan_date',
        'item_id',
        'proc_id',
        'qty',
        'machine_id',
        'status',
    ];

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function machine()
    {
        return $this->belongsTo(m_machine::class, 'machine_id', 'id');
    }

    public function process()
    {
        return $this->belongsTo(m_process::class, 'proc_id', 'id');
    }
}
