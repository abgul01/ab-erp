<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sub_po_detail extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'sub_po_detail';

    protected $primaryKey = 'id';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'main_id',
        'item_id',
        'process_id',
        'qty',
        'price',
    ];

    public function main()
    {
        return $this->belongsTo(sub_po_main::class, 'main_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
