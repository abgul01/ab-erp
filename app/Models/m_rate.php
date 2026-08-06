<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_rate extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'm_rate';

    protected $primaryKey = 'id';

    public $incrementing = true;

    public $timestamps = false;   // m_rate carries no created_at/updated_at

    protected $fillable = [
        'currency_id',
        'rate_type',
        'valid_date',
        'rate',
    ];

    protected $casts = [
        'valid_date' => 'date',
        'rate' => 'float',
    ];

    public function currency()
    {
        return $this->belongsTo(m_currency::class, 'currency_id', 'id');
    }
}
