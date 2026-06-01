<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    public static function item(string $key, mixed $data, int $status = 200, ?string $message = null, array $extra = []): JsonResponse
    {
        return response()->json(array_filter([
            'data' => $data,
            $key => $data,
            'message' => $message,
        ], fn ($value) => $value !== null) + $extra, $status);
    }

    public static function collection(string $key, mixed $data, int $status = 200, ?string $message = null, array $extra = []): JsonResponse
    {
        return self::item($key, $data, $status, $message, $extra);
    }

    public static function paginated(string $key, LengthAwarePaginator $paginator, int $status = 200, ?string $resourceClass = null): JsonResponse
    {
        if ($resourceClass) {
            $paginator->setCollection($resourceClass::collection($paginator->getCollection())->collection);
        }

        return response()->json([
            'data' => $paginator->items(),
            $key => $paginator,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ], $status);
    }

    public static function ok(array $payload = [], ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json(array_filter([
            'data' => $payload,
            'message' => $message,
        ], fn ($value) => $value !== null) + $payload, $status);
    }
}
