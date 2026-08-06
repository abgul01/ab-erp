<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class m_fg_downgrade_map extends Model
{
    protected $connection = 'mysql';

    protected $table = 'm_fg_downgrade_map';

    protected $fillable = [
        'fg_item_id',
        'material_item_id',
        'note',
    ];

    public function fgItem()
    {
        return $this->belongsTo(m_item::class, 'fg_item_id');
    }

    public function materialItem()
    {
        return $this->belongsTo(m_item::class, 'material_item_id');
    }
}
