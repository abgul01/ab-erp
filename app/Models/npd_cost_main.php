<?php

namespace App\Models;

use App\Support\HasApproval;
use Illuminate\Database\Eloquent\Model;

/**
 * Estimasi biaya dan quotation.
 *
 * Perlu persetujuan sebelum harganya dikirim ke pelanggan, dan hanya yang
 * APPROVED yang boleh dijadikan pricelist (keputusan 4 Agustus 2026).
 */
class npd_cost_main extends Model
{
    use HasApproval;

    protected $connection = 'mysql';

    protected $table = 'npd_cost_main';

    protected $fillable = [
        'main_id', 'bom_id', 'version', 'period', 'material_cost', 'process_cost',
        'tooling_cost', 'overhead', 'margin_pct', 'total_cost', 'quoted_price',
        'status', 'approved_by', 'pricelist_det_id', 'note', 'user_id',
    ];

    protected $casts = [
        'material_cost' => 'float', 'process_cost' => 'float', 'tooling_cost' => 'float',
        'overhead' => 'float', 'margin_pct' => 'float', 'total_cost' => 'float',
        'quoted_price' => 'float',
    ];

    public function detail()
    {
        return $this->hasMany(npd_cost_det::class, 'main_id', 'id');
    }

    public function project()
    {
        return $this->belongsTo(npd_project::class, 'main_id', 'id');
    }
}
