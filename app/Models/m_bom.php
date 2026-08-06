<?php

namespace App\Models;

use App\Support\HasApproval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_bom extends Model
{
    use HasApproval;
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'm_bom';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $fillable = [
        'status',
        'item_id',
        'active',
    ];

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function detail()
    {
        return $this->hasMany(m_bom_det_pm::class, 'id_prim', 'id');
    }

    public function m_bom_det_rm()
    {
        return $this->hasMany(m_bom_det_rm::class, 'id_prim', 'id');
    }

    // Clearer aliases used by the API
    public function rmLines()
    {
        return $this->hasMany(m_bom_det_rm::class, 'id_prim', 'id');
    }

    public function pmLines()
    {
        return $this->hasMany(m_bom_det_pm::class, 'id_prim', 'id');
    }
}
