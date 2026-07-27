<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_pricelist_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_pricelist_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'cus_id',
        'status',
        'user_id',
    ];

    public function cus()
    {
        return $this->belongsTo(m_contacts::class, 'cus_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function detail()
    {
        return $this->hasMany(m_pricelist_det::class, 'main_id', 'id');
    }
}
