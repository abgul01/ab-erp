<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sub_dn_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'sub_dn_main';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $fillable = [
        'code',
        'date',
        'po_id',
        'ven_id',
        'user_id',
        'status',
    ];

    public function po()
    {
        return $this->belongsTo(sub_po_main::class, 'po_id', 'id');
    }

    public function ven()
    {
        return $this->belongsTo(m_contacts::class, 'ven_id', 'id');
    }

    public function detail()
    {
        return $this->hasMany(sub_dn_detail::class, 'main_id', 'id');
    }
}
