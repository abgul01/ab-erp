<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_tax extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_tax';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'rate_pct',
        'dpp_factor',
        'is_luxury',
        'effective_from'
    ];

    public function sls_so_detail()
    {
        return $this->hasMany(sls_so_detail::class, 'tax_id', 'id');
    }
}
