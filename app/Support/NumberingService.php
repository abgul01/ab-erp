<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Atomic, race-safe document numbering (LLD 4.1).
 * Format: PREFIX/YYYY/MM/00001 — sequence resets monthly per doc_type.
 */
class NumberingService
{
    public function next(string $docType, string $prefix): string
    {
        return DB::transaction(function () use ($docType, $prefix) {
            $period = now()->format('Ym');

            $row = DB::table('doc_numberings')
                ->where('doc_type', $docType)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                DB::table('doc_numberings')->insert([
                    'doc_type' => $docType,
                    'prefix' => $prefix,
                    'period' => $period,
                    'last_number' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $row = DB::table('doc_numberings')
                    ->where('doc_type', $docType)
                    ->where('period', $period)
                    ->lockForUpdate()
                    ->first();
            }

            $next = $row->last_number + 1;

            DB::table('doc_numberings')
                ->where('id', $row->id)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return sprintf('%s/%s/%s/%05d', $prefix, now()->format('Y'), now()->format('m'), $next);
        });
    }
}
