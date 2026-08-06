<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Lintasan produksi: sekumpulan mesin yang kapasitasnya dinilai bersama. */
class m_production_line extends Model
{
    protected $connection = 'mysql';

    protected $table = 'm_production_line';

    protected $fillable = ['code', 'name', 'descrip', 'daily_hours', 'active'];

    protected $casts = ['daily_hours' => 'float', 'active' => 'boolean'];

    public function machines()
    {
        return $this->hasMany(m_machine::class, 'line_id', 'id');
    }
}
