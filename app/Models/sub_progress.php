<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** A subcontractor's progress report against one PO line. */
class sub_progress extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'sub_progress';

    protected $primaryKey = 'id';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'po_detail_id',
        'progress_pct',
        'note',
        'user_id',
        'reported_at',
    ];

    protected $casts = [
        'progress_pct' => 'float',
        'reported_at' => 'datetime',
    ];

    public function detail()
    {
        return $this->belongsTo(sub_po_detail::class, 'po_detail_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
