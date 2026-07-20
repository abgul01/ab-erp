<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prc_gr_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prc_gr_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'ven_id',
        'date',
        'user_id',
        'po_no',
        'import_doc_no',
        'status'
    ];

    public function ven()
    {
        return $this->belongsTo(m_contacts::class, 'ven_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function detail()
    {
        return $this->hasMany(prc_gr_detail::class, 'id_prim', 'id');
    }

    public function prc_gr_reject()
    {
        return $this->hasMany(prc_gr_reject::class, 'gr_id', 'id');
    }

    public function qc_incoming_main()
    {
        return $this->hasMany(qc_incoming_main::class, 'gr_id', 'id');
    }

    public function wh_inc_main()
    {
        return $this->hasMany(wh_inc_main::class, 'gr_id', 'id');
    }
}
