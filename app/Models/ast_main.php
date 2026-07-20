<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ast_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'ast_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'name',
        'useful_life',
        'gr_detail_id',
        'status'
    ];

    public function gr_detail()
    {
        return $this->belongsTo(prc_gr_detail::class, 'gr_detail_id', 'id');
    }

    public function wh_gen_req_det()
    {
        return $this->hasMany(wh_gen_req_det::class, 'asset_id', 'id');
    }
}
