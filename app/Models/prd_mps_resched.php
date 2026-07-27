<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prd_mps_resched extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prd_mps_resched';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'mps_id',
        'from_date',
        'from_machine_id',
        'to_date',
        'to_machine_id',
        'reason',
        'status',
        'requested_by',
        'approved_by',
        'decision_note',
        'decided_at',
    ];

    public function mps()
    {
        return $this->belongsTo(prd_mps::class, 'mps_id', 'id');
    }

    public function fromMachine()
    {
        return $this->belongsTo(m_machine::class, 'from_machine_id', 'id');
    }

    public function toMachine()
    {
        return $this->belongsTo(m_machine::class, 'to_machine_id', 'id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by', 'id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }
}
