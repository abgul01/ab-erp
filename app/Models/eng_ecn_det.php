<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One field of one row an ECN changes: what it was, what it becomes. */
class eng_ecn_det extends Model
{
    protected $connection = 'mysql';

    protected $table = 'eng_ecn_det';

    public $timestamps = false;

    protected $fillable = [
        'main_id', 'action', 'target_table', 'target_id',
        'field', 'old_value', 'new_value', 'note',
    ];

    public function main()
    {
        return $this->belongsTo(eng_ecn_main::class, 'main_id', 'id');
    }
}
