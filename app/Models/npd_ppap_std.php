<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu dari 18 elemen PPAP baku (AIAG). */
class npd_ppap_std extends Model
{
    protected $connection = 'mysql';

    protected $table = 'npd_ppap_std';

    protected $fillable = ['element_no', 'name', 'descrip', 'level_required'];

    protected $casts = ['element_no' => 'integer'];

    /** Apakah elemen ini wajib untuk level PPAP tertentu. */
    public function requiredForLevel(int $level): bool
    {
        return in_array((string) $level, explode(',', $this->level_required), true);
    }
}
