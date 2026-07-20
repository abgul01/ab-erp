<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sub_progress extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'sub_progress';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'progress_pct',
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
