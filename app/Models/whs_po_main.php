<?php

namespace App\Models;

use App\Support\HasApproval;
use Illuminate\Database\Eloquent\Model;

/** Purchase Order gudang WHS — terpisah dari PO produksi. */
class whs_po_main extends Model
{
    use HasApproval;

    protected $connection = 'mysql';

    protected $table = 'whs_po_main';

    protected $fillable = [
        'code', 'date', 'ven_id', 'currency_id', 'rate', 'top_days',
        'eta', 'status', 'note', 'user_id',
    ];

    protected $casts = ['date' => 'date:Y-m-d', 'eta' => 'date:Y-m-d', 'rate' => 'float'];

    public function detail()
    {
        return $this->hasMany(whs_po_det::class, 'main_id', 'id');
    }

    public function ven()
    {
        return $this->belongsTo(m_contacts::class, 'ven_id', 'id');
    }
}
