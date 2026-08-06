<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-aware writer for the demo seed.
 *
 * The legacy tables are inconsistent — some carry timestamps, some carry only
 * created_at, some neither — and a seeder that hardcodes those differences
 * breaks the moment a table is touched. This asks the schema instead: unknown
 * keys are dropped and timestamps are filled only where the columns exist.
 *
 * Dropping unknown keys is safe here precisely because this is demo data: the
 * intent is "insert what this table can hold", not "insert exactly this shape".
 */
class SeedWriter
{
    /** @var array<string, array<string>> table => columns */
    private static array $columns = [];

    private static function columns(string $table): array
    {
        return self::$columns[$table] ??= Schema::getColumnListing($table);
    }

    /**
     * Insert or update one row, keyed on id when the row carries one.
     *
     * @param  \DateTimeInterface|string|null  $at  timestamp for created_at/updated_at
     */
    public static function put(string $table, array $row, $at = null): void
    {
        $columns = self::columns($table);
        $at ??= $row['created_at'] ?? now();

        foreach (['created_at', 'updated_at'] as $stamp) {
            if (in_array($stamp, $columns, true) && ! array_key_exists($stamp, $row)) {
                $row[$stamp] = $at;
            }
        }

        $row = array_intersect_key($row, array_flip($columns));

        if (isset($row['id'])) {
            DB::table($table)->updateOrInsert(['id' => $row['id']], $row);

            return;
        }

        DB::table($table)->insert($row);
    }

    /** Same, for a list of rows. */
    public static function putMany(string $table, array $rows, $at = null): void
    {
        foreach ($rows as $row) {
            self::put($table, $row, $at);
        }
    }
}
