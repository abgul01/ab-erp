<?php

namespace App\Models;

use App\Support\HasApproval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A reusable routing template: a named, ordered set of processes that many items
 * can share (m_process_main_det holds the steps).
 */
class m_process_main extends Model
{
    use HasApproval;
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'm_process_main';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $fillable = [
        'status',
        'code',
        'name',
        'active',
    ];

    public function detail()
    {
        return $this->hasMany(m_process_main_det::class, 'main_id', 'id');
    }
}
