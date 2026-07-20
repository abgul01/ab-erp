<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prc_cost_main extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'prc_cost_main';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'code',
        'date',
        'po_id',
        'gr_id',
        'inv_id',
        'alloc_basis',
        'status',
        'user_id',
    ];

    public function po()
    {
        return $this->belongsTo(prc_po_main::class, 'po_id', 'id');
    }

    public function gr()
    {
        return $this->belongsTo(prc_gr_main::class, 'gr_id', 'id');
    }

    public function inv()
    {
        return $this->belongsTo(prc_inv_main::class, 'inv_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function detail()
    {
        return $this->hasMany(prc_cost_detail::class, 'main_id', 'id');
    }

    public function alloc()
    {
        return $this->hasMany(prc_cost_alloc::class, 'main_id', 'id');
    }
}
