<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_bom_pro extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_bom_pro';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'item_id',
        'process_main_id',
        'priority',
    ];

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    /** The routing template this item follows (Master Process Main). */
    public function processMain()
    {
        return $this->belongsTo(m_process_main::class, 'process_main_id', 'id');
    }

    /** Legacy per-item steps — no longer written; kept for old data. */
    public function detail()
    {
        return $this->hasMany(m_bom_pro_det::class, 'id_prim', 'id');
    }
}
