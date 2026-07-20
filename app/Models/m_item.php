<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class m_item extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'm_item';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'part_name',
        'type',
        'descrip',
        'category_id',
        'o_d',
        'i_d',
        'thick',
        'width',
        'height',
        'length',
        'length_cut',
        'weight',
        'tolerance',
        'min_stock',
        'max_stock',
        'pm',
        'active',


    ];



    public function m_bom()
    {
        return $this->hasMany(m_bom::class, 'item_id', 'id');
    }

    public function m_bom_pro()
    {
        return $this->hasMany(m_bom_pro::class, 'item_id', 'id');
    }

    public function m_i_pm()
    {
        return $this->hasMany(m_i_pm::class, 'item_id', 'id');
    }

    public function m_pal_item()
    {
        return $this->hasMany(m_pal_item::class, 'item_id', 'id');
    }

    public function prc_gr_detail()
    {
        return $this->hasMany(prc_gr_detail::class, 'item_id', 'id');
    }

    public function prc_po_detail()
    {
        return $this->hasMany(prc_po_detail::class, 'item_id', 'id');
    }

    public function prd_cut_main()
    {
        return $this->hasMany(prd_cut_main::class, 'item_id', 'id');
    }

    public function prd_wip()
    {
        return $this->hasMany(prd_wip::class, 'item_id', 'id');
    }

    public function prd_wo_detail_rm()
    {
        return $this->hasMany(prd_wo_detail_rm::class, 'rm_id', 'id');
    }

    public function prd_wo_main()
    {
        return $this->hasMany(prd_wo_main::class, 'fg_id', 'id');
    }

    public function sls_do_detail()
    {
        return $this->hasMany(sls_do_detail::class, 'item_id', 'id');
    }

    public function sls_inv_detail()
    {
        return $this->hasMany(sls_inv_detail::class, 'item_id', 'id');
    }

    public function stock_check()
    {
        return $this->hasMany(stock_check::class, 'item_id', 'id');
    }

    public function sub_dn_detail()
    {
        return $this->hasMany(sub_dn_detail::class, 'item_id', 'id');
    }

    public function sum_stock_pm()
    {
        return $this->hasMany(sum_stock_pm::class, 'item_id', 'id');
    }

    public function sum_stock_rm()
    {
        return $this->hasMany(sum_stock_rm::class, 'item_id', 'id');
    }

    public function tr_ab_cut_main()
    {
        return $this->hasMany(tr_ab_cut_main::class, 'item_id', 'id');
    }

    public function tr_ab_pro()
    {
        return $this->hasMany(tr_ab_pro::class, 'item_id', 'id');
    }

    public function tr_cut_main()
    {
        return $this->hasMany(tr_cut_main::class, 'item_id', 'id');
    }

    public function tr_inc_fg_det()
    {
        return $this->hasMany(tr_inc_fg_det::class, 'item_id', 'id');
    }

    public function tr_inc_fg_det_udf()
    {
        return $this->hasMany(tr_inc_fg_det_udf::class, 'item_id', 'id');
    }

    public function tr_out_fg_det()
    {
        return $this->hasMany(tr_out_fg_det::class, 'item_id', 'id');
    }

    public function tr_pro_main()
    {
        return $this->hasMany(tr_pro_main::class, 'item_id', 'id');
    }

    public function wh_gen_out_det()
    {
        return $this->hasMany(wh_gen_out_det::class, 'item_id', 'id');
    }

    public function wh_inc_detail()
    {
        return $this->hasMany(wh_inc_detail::class, 'item_id', 'id');
    }

    public function wh_out_main()
    {
        return $this->hasMany(wh_out_main::class, 'item_id', 'id');
    }

    public function wh_rem_main()
    {
        return $this->hasMany(wh_rem_main::class, 'item_id', 'id');
    }

    public function wip()
    {
        return $this->hasMany(wip::class, 'item_id', 'id');
    }
}
