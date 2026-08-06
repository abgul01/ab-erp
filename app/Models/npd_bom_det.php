<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu material pada preliminary BOM; boleh menunjuk part yang belum terdaftar. */
class npd_bom_det extends Model
{
    protected $connection = 'mysql';

    protected $table = 'npd_bom_det';

    public $timestamps = false;

    protected $fillable = [
        'main_id', 'item_id', 'new_item_code', 'new_item_name', 'role',
        'qty', 'length_use', 'uom_id', 'ven_id', 'unit_cost', 'cost_source', 'note',
    ];

    protected $casts = ['qty' => 'float', 'length_use' => 'float', 'unit_cost' => 'float'];

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function ven()
    {
        return $this->belongsTo(m_contacts::class, 'ven_id', 'id');
    }

    /** Nama yang ditampilkan: master bila ada, kalau tidak nama sementaranya. */
    public function label(): string
    {
        return $this->item
            ? "{$this->item->code} — {$this->item->part_name}"
            : trim(($this->new_item_code ?? '').' — '.($this->new_item_name ?? 'part baru'), ' —');
    }
}
