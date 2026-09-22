<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class fin_ap_expenses extends Model
{
    protected $table = 'fin_ap_expenses';
    protected $guarded = ['id'];
    
    public function details() {
        return $this->hasMany(fin_ap_expense_det::class, 'expense_id');
    }
}
