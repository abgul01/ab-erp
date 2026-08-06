<?php

namespace App\Models;

use App\Support\HasApproval;
use Illuminate\Database\Eloquent\Model;

/** Engineering Change Notice — a change to an item, BOM or routing, agreed before it happens. */
class eng_ecn_main extends Model
{
    use HasApproval;

    protected $connection = 'mysql';

    protected $table = 'eng_ecn_main';

    protected $fillable = [
        'code', 'date', 'change_type', 'item_id', 'reason', 'impact',
        'effective_date', 'status', 'user_id', 'applied_by', 'applied_at',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'effective_date' => 'date:Y-m-d',
        'applied_at' => 'datetime',
    ];

    public function detail()
    {
        return $this->hasMany(eng_ecn_det::class, 'main_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(m_item::class, 'item_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Approval moves it to APPROVED, not to APPLIED.
     *
     * Agreeing to a change and making it are different acts: the notice carries
     * an effective date, and a change approved in July that takes effect in
     * September must not touch the master in July.
     */
    public function approvedStatus(): mixed
    {
        return 'APPROVED';
    }
}
