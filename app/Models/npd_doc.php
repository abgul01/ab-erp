<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Dokumen proyek dengan revisi; revisi lama tidak dihapus, hanya tidak berlaku. */
class npd_doc extends Model
{
    protected $connection = 'mysql';

    protected $table = 'npd_doc';

    protected $fillable = [
        'main_id', 'ref_type', 'ref_id', 'doc_type', 'file_name', 'file_path',
        'mime', 'size_kb', 'version', 'is_current', 'user_id',
    ];

    protected $casts = ['is_current' => 'boolean', 'size_kb' => 'integer'];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
