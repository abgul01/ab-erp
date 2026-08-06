<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Master: which items (and process/price) a subcont vendor handles. */
class m_subcont_item extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'm_subcont_item';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $fillable = [
        'ven_id',
        'item_id',
        'process_id',
        'price',
        'active',
    ];

    public function ven()
    {
        return $this->belongsTo(m_contacts::class, 'ven_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function process()
    {
        return $this->belongsTo(m_process::class, 'process_id', 'id');
    }
}
