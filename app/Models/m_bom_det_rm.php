<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_bom_det_rm extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_bom_det_rm';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'id_prim',
        'mat_id',
        'length_cut',
        'length_use',
        'priority'
    ];

    public function main()
    {
        return $this->belongsTo(m_bom::class, 'id_prim', 'id');
    }

    public function material()
    {
        return $this->belongsTo(m_item::class, 'mat_id', 'id');
    }
}
