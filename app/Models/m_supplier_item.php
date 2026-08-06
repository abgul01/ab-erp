<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What one supplier offers for one material: price, minimum, lot and lead time. */
class m_supplier_item extends Model
{
    protected $connection = 'mysql';

    protected $table = 'm_supplier_item';

    protected $fillable = [
        'ven_id', 'item_id', 'priority', 'price', 'currency_id',
        'moq', 'order_lot', 'lead_time_days', 'supplier_part_no',
        'valid_from', 'valid_to', 'active',
        // Asal-usul syarat beli: penawaran yang dipilih atau kontrak yang mengunci.
        'quot_det_id', 'contract_id',
    ];

    protected $casts = [
        'price' => 'float',
        'moq' => 'integer',
        'order_lot' => 'integer',
        'lead_time_days' => 'integer',
        'priority' => 'integer',
        'active' => 'boolean',
        'valid_from' => 'date:Y-m-d',
        'valid_to' => 'date:Y-m-d',
    ];

    public function vendor()
    {
        return $this->belongsTo(m_contacts::class, 'ven_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }
}
