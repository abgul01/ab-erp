<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class qc_incoming_det extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'qc_incoming_det';

    protected $primaryKey = 'id';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'main_id',
        'param',
        'standard',
        'actual',
        'judge',
    ];

    public function main()
    {
        return $this->belongsTo(qc_incoming_main::class, 'main_id', 'id');
    }
}
