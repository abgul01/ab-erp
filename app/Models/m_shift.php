<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_shift extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_shift';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name'
    ];

    public function wh_inc_main()
    {
        return $this->hasMany(wh_inc_main::class, 'shift_id', 'id');
    }

    public function wh_out_main()
    {
        return $this->hasMany(wh_out_main::class, 'shift_id', 'id');
    }

    public function wh_rem_main()
    {
        return $this->hasMany(wh_rem_main::class, 'shift_id', 'id');
    }
}
