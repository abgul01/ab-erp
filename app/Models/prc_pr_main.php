<?php

namespace App\Models;

use App\Support\HasApproval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prc_pr_main extends Model
{
    use HasApproval, HasFactory;

    protected $connection = 'mysql';

    protected $table = 'prc_pr_main';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $fillable = [
        'code',
        'date',
        'pr_type',
        'user_id',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function detail()
    {
        return $this->hasMany(prc_pr_detail::class, 'main_id', 'id');
    }
}
