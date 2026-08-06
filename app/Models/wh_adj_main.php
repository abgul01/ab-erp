<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Stock opname / adjustment header. */
class wh_adj_main extends Model
{
    protected $connection = 'mysql';

    protected $table = 'wh_adj_main';

    protected $fillable = [
        'code', 'date', 'adj_type', 'warehouse', 'reason',
        'status', 'user_id', 'posted_by', 'posted_at',
    ];

    protected $casts = ['posted_at' => 'datetime'];

    public function detail()
    {
        return $this->hasMany(wh_adj_detail::class, 'main_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
