<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Barang gudang WHS: sparepart, barang habis pakai, atau alat kerja. */
class m_whs_item extends Model
{
    public const TYPES = ['PART', 'CONSUMABLE', 'TOOL'];

    protected $connection = 'mysql';

    protected $table = 'm_whs_item';

    protected $fillable = [
        'code', 'name', 'whs_type', 'categ', 'uom_id', 'brand', 'spec',
        'rack_loc', 'min_stock', 'max_stock', 'standard_cost', 'active',
    ];

    protected $casts = [
        'min_stock' => 'integer',
        'max_stock' => 'integer',
        'standard_cost' => 'float',
        'active' => 'boolean',
    ];

    public function uom()
    {
        return $this->belongsTo(m_uom::class, 'uom_id', 'id');
    }

    public function units()
    {
        return $this->hasMany(whs_tool_unit::class, 'item_id', 'id');
    }

    /** Alat kembali ke gudang; dua jenis lainnya habis begitu keluar. */
    public function isTool(): bool
    {
        return $this->whs_type === 'TOOL';
    }
}
