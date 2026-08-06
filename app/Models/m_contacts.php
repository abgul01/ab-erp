<?php

namespace App\Models;

use App\Support\HasApproval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_contacts extends Model
{
    use HasApproval;
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'm_contacts';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $fillable = [
        'status',
        'u_code',
        'initial',
        'nick_n',
        'company_n',
        'address',
        'name',
        'phone',
        'email',
        'category_id',
        'identity',
        'npwp',
        'nik',
        'active',
    ];

    public function acc_ap_pay_main()
    {
        return $this->hasMany(acc_ap_pay_main::class, 'ven_id', 'id');
    }

    public function acc_ar_rec_main()
    {
        return $this->hasMany(acc_ar_rec_main::class, 'cus_id', 'id');
    }

    public function m_item()
    {
        return $this->hasMany(m_item::class, 'cus_id', 'id');
    }

    public function m_item_customer()
    {
        return $this->hasMany(m_item_customer::class, 'cus_id', 'id');
    }

    public function m_pricelist_main()
    {
        return $this->hasMany(m_pricelist_main::class, 'cus_id', 'id');
    }

    public function prc_gr_main()
    {
        return $this->hasMany(prc_gr_main::class, 'ven_id', 'id');
    }

    public function prc_inv_main()
    {
        return $this->hasMany(prc_inv_main::class, 'ven_id', 'id');
    }

    public function prc_po_main()
    {
        return $this->hasMany(prc_po_main::class, 'ven_id', 'id');
    }

    public function prd_wo_main()
    {
        return $this->hasMany(prd_wo_main::class, 'customer_id', 'id');
    }

    public function sls_inv_main()
    {
        return $this->hasMany(sls_inv_main::class, 'cus_id', 'id');
    }

    public function sls_so_main()
    {
        return $this->hasMany(sls_so_main::class, 'cus_id', 'id');
    }

    public function wh_out_main()
    {
        return $this->hasMany(wh_out_main::class, 'cus_id', 'id');
    }
}
