<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prd_kanban extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'prd_kanban';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $fillable = [
        'code',
        'wo_id',
        'item_id',
        'rm_detail_id',
        'qty_planned',
        'qty_issued',
        'status',
        'created_by',
        'issued_by',
        'issued_at',
        'closed_by',
        'closed_at',
    ];

    public function wo()
    {
        return $this->belongsTo(prd_wo_main::class, 'wo_id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id');
    }
}
