<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prd_wip extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prd_wip';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'no_dp',
        'wo_id',
        'item_id',
        'user_id',
        'date'
    ];

    public function wo()
    {
        return $this->belongsTo(prd_wo_main::class, 'wo_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
