<?php

namespace App\Models;

use App\Support\HasApproval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ast_main extends Model
{
    use HasApproval;
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'ast_main';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $fillable = [
        'code',
        'categ_id',
        'name',
        'acq_date',
        'acq_cost',
        'useful_life',
        'po_id',
        'gr_detail_id',
        'machine_id',
        'status',
    ];

    public function categ()
    {
        return $this->belongsTo(m_asset_categ::class, 'categ_id', 'id');
    }

    public function depre()
    {
        return $this->hasMany(ast_depre::class, 'ast_id', 'id');
    }

    public function gr_detail()
    {
        return $this->belongsTo(prc_gr_detail::class, 'gr_detail_id', 'id');
    }

    /** Sparepart & alat yang dikeluarkan gudang WHS untuk aset ini. */
    public function whs_out_det()
    {
        return $this->hasMany(whs_out_det::class, 'asset_id', 'id');
    }
}
