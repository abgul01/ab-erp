<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Status satu elemen PPAP pada satu submission. */
class npd_ppap_det extends Model
{
    protected $connection = 'mysql';

    protected $table = 'npd_ppap_det';

    protected $fillable = ['main_id', 'std_id', 'status', 'doc_id', 'auto_source', 'note'];

    public function std()
    {
        return $this->belongsTo(npd_ppap_std::class, 'std_id', 'id');
    }

    public function doc()
    {
        return $this->belongsTo(npd_doc::class, 'doc_id', 'id');
    }
}
