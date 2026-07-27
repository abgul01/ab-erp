<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_route_time extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_route_time';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'item_id',
        'proc_id',
        'machine_id',
        'cycle_sec',
        'setup_min',
        'priority',
        'active',
    ];

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function process()
    {
        return $this->belongsTo(m_process::class, 'proc_id', 'id');
    }

    public function machine()
    {
        return $this->belongsTo(m_machine::class, 'machine_id', 'id');
    }
}
