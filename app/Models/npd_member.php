<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class npd_member extends Model
{
    public const ROLES = ['PM', 'DESIGN', 'PROCESS', 'QUALITY', 'PROCUREMENT', 'COSTING', 'VIEWER'];

    protected $connection = 'mysql';

    protected $table = 'npd_member';

    protected $fillable = ['main_id', 'user_id', 'role'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
