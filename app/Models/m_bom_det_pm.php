<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_bom_det_pm extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_bom_det_pm';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'id_prim',
        'pm_id',
        'qty'
    ];

    public function main()
    {
        return $this->belongsTo(m_bom::class, 'id_prim', 'id');
    }

    public function part()
    {
        return $this->belongsTo(m_item::class, 'pm_id', 'id');
    }
}
