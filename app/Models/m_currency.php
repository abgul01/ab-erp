<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_currency extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_currency';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'is_base'
    ];

    public function prc_cost_detail()
    {
        return $this->hasMany(prc_cost_detail::class, 'currency_id', 'id');
    }

    public function prc_po_main()
    {
        return $this->hasMany(prc_po_main::class, 'currency_id', 'id');
    }
}
