<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Penerimaan barang WHS. Stok baru bergerak saat dokumen ini di-post. */
class whs_inc_main extends Model
{
    protected $connection = 'mysql';

    protected $table = 'whs_inc_main';

    protected $fillable = [
        'code', 'date', 'po_id', 'ven_id', 'do_no', 'note',
        'status', 'posted_at', 'user_id',
    ];

    protected $casts = ['date' => 'date:Y-m-d', 'posted_at' => 'datetime'];

    public function detail()
    {
        return $this->hasMany(whs_inc_det::class, 'main_id', 'id');
    }

    public function po()
    {
        return $this->belongsTo(whs_po_main::class, 'po_id', 'id');
    }

    public function ven()
    {
        return $this->belongsTo(m_contacts::class, 'ven_id', 'id');
    }
}
