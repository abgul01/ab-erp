<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

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

    public static function created(mixed $data = null): JsonResponse
    {
        return response()->json(['data' => $data], 201);
    }

    public static function ok(mixed $data = null): JsonResponse
    {
        return response()->json(['data' => $data], 200);
    }

    public static function notFound(string $message = 'Not found'): JsonResponse
    {
        return response()->json(['error' => $message], 404);
    }

    public static function conflict(string $message = 'Conflict', mixed $extra = null): JsonResponse
    {
        $payload = ['error' => $message];
        if ($extra !== null) {
            $payload['data'] = $extra;
        }

        return response()->json($payload, 409);
    }
}
