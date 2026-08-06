<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only log of MES operations that arrived from a terminal.
 * The unique client_uuid is what makes an offline replay idempotent.
 *
 * LLD §6.2
 */
class mes_oplog extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'mes_oplog';

    protected $fillable = [
        'client_uuid', 'type', 'method', 'url',
        'table_name', 'row_id', 'user_id',
        'payload', 'status', 'error', 'client_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'row_id' => 'integer',
            'user_id' => 'integer',
            'client_at' => 'datetime',
        ];
    }
}
