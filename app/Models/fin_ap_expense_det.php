<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class fin_ap_expense_det extends Model
{
    protected $table = 'fin_ap_expense_det';
    protected $guarded = ['id'];
    
    public function main() {
        return $this->belongsTo(fin_ap_expenses::class, 'expense_id');
    }
}
