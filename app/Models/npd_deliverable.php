<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Keluaran APQP yang harus ada pada sebuah fase. */
class npd_deliverable extends Model
{
    protected $connection = 'mysql';

    protected $table = 'npd_deliverable';

    protected $fillable = [
        'main_id', 'std_id', 'title', 'status', 'resp_user_id',
        'due_date', 'submitted_date', 'doc_id', 'note',
    ];

    protected $casts = ['due_date' => 'date:Y-m-d', 'submitted_date' => 'date:Y-m-d'];

    public function std()
    {
        return $this->belongsTo(npd_deliverable_std::class, 'std_id', 'id');
    }

    public function doc()
    {
        return $this->belongsTo(npd_doc::class, 'doc_id', 'id');
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'resp_user_id', 'id');
    }

    /** Selesai atau dikecualikan — keduanya melepas kunci gate. */
    public function isSettled(): bool
    {
        return in_array($this->status, ['DONE', 'WAIVED'], true);
    }
}
