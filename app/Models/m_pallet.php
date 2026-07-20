<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_pallet extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_pallet';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'name_pa',
        'type_id',
        'size',
        'cap_kg',
        'cap_m3',
        'note',
        'active'
    ];

    public function type()
    {
        return $this->belongsTo(m_p_type::class, 'type_id', 'id');
    }

    public function m_pal_item_det()
    {
        return $this->hasMany(m_pal_item_det::class, 'pal_id', 'id');
    }
}
