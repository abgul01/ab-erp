<?php

namespace App\Models;

use App\Support\HasApproval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Subcontract purchase order — separate document from the general PO. */
class sub_po_main extends Model
{
    use HasApproval;
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'sub_po_main';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $fillable = [
        'code',
        'date',
        'ven_id',
        'user_id',
        'status',
        'note',
    ];

    public function ven()
    {
        return $this->belongsTo(m_contacts::class, 'ven_id', 'id');
    }

    public function detail()
    {
        return $this->hasMany(sub_po_detail::class, 'main_id', 'id');
    }
}
