<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * Standard success envelope helpers: { data, meta }.
 */
class ApiResponse
{
    public static function item(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    public static function paginated(LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public static function collection(iterable $data): JsonResponse
    {
        return response()->json(['data' => $data]);
    }

    public static function empty(int $status = 204): JsonResponse
    {
        return response()->json(null, $status);
    }
}
