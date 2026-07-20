<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_p_type extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_p_type';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'name_ty'
    ];

    public function m_pallet()
    {
        return $this->hasMany(m_pallet::class, 'type_id', 'id');
    }
}
