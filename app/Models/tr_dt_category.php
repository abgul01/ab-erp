<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_dt_category extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_dt_category';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'name_c_dt',
        'descriptions'
    ];

    public function tr_dt_cut_main()
    {
        return $this->hasMany(tr_dt_cut_main::class, 'cat_id', 'id');
    }

    public function tr_dt_pro_main()
    {
        return $this->hasMany(tr_dt_pro_main::class, 'cat_id', 'id');
    }
}
