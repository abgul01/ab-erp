<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tr_out_fg_det extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'tr_out_fg_det';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'main_id',
        'item_id',
        'fg_code',
        'code',
        'qty'
    ];

    public function main()
    {
        return $this->belongsTo(tr_out_fg_main::class, 'main_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
