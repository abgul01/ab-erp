<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prc_po_att extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prc_po_att';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'main_id',
        'file_type',
        'file_path',
        'file_name',
        'created_at',
    ];

    public function main()
    {
        return $this->belongsTo(prc_po_main::class, 'main_id', 'id');
    }
}
