<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Master 5 fase APQP. */
class npd_phase extends Model
{
    protected $connection = 'mysql';

    protected $table = 'npd_phase';

    protected $fillable = ['phase_no', 'name', 'descrip'];

    public function standards()
    {
        return $this->hasMany(npd_deliverable_std::class, 'phase_id', 'id')->orderBy('sort');
    }
}
