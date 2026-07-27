<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prd_mrp_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prd_mrp_main';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;   // has created_at only, set explicitly

    protected $fillable = [
        'run_date',
        'user_id',
        'status',
        'created_at',
    ];

    public function detail()
    {
        return $this->hasMany(prd_mrp_detail::class, 'main_id', 'id');
    }
}
