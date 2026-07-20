<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sum_stock_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'sum_stock_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'date'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function detail()
    {
        return $this->hasMany(sum_stock_pm::class, 'main_id', 'id');
    }

    public function sum_stock_rm()
    {
        return $this->hasMany(sum_stock_rm::class, 'main_id', 'id');
    }
}
