<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu unit alat, dilacak sendiri.
 *
 * Jumlah tidak cukup untuk alat: yang ditanyakan orang gudang selalu "unit yang
 * mana, sekarang di siapa" — dan itu hanya bisa dijawab kalau tiap unit punya
 * baris sendiri.
 */
class whs_tool_unit extends Model
{
    public const IN_STOCK = 'IN_STOCK';

    public const ON_LOAN = 'ON_LOAN';

    protected $connection = 'mysql';

    protected $table = 'whs_tool_unit';

    protected $fillable = [
        'item_id', 'code', 'inc_det_id', 'status', 'holder',
        'out_det_id', 'unit_cost', 'note',
    ];

    protected $casts = ['unit_cost' => 'float'];

    public function item()
    {
        return $this->belongsTo(m_whs_item::class, 'item_id', 'id');
    }
}
