<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Builds Reton's single, consistent API envelope.
 *
 * Every JSON response from /api/v1 has the shape:
 *   { success, message, data, errors, meta }
 * so clients can rely on one contract for success, validation and error alike.
 */
final class ApiResponse
{
    /**
     * @param  mixed  $data
     */
    public static function success($data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return self::envelope(true, $message, $data, null, null, $status);
    }

    /**
     * @param  mixed  $data
     */
    public static function created($data = null, string $message = 'Created'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    public static function noContent(string $message = 'OK'): JsonResponse
    {
        return self::success(null, $message, 200);
    }

    /**
     * @param  array<string, list<string>>|null  $errors
     */
    public static function error(
        string $message,
        string $code = 'error',
        int $status = 400,
        ?array $errors = null
    ): JsonResponse {
        return self::envelope(false, $message, null, $errors, $code, $status);
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function validationError(array $errors, string $message = 'The given data was invalid.'): JsonResponse
    {
        return self::error($message, 'validation_error', 422, $errors);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        ?JsonResource $resource = null,
        string $message = 'OK'
    ): JsonResponse {
        $items = $resource ?? $paginator->items();

        return self::envelope(true, $message, $items, null, null, 200, [
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * @param  mixed  $data
     * @param  array<string, list<string>>|null  $errors
     * @param  array<string, mixed>|null  $meta
     */
    private static function envelope(
        bool $success,
        string $message,
        $data,
        ?array $errors,
        ?string $code,
        int $status,
        ?array $meta = null
    ): JsonResponse {
        $payload = [
            'success' => $success,
            'message' => $message,
            'data' => $data instanceof JsonResource
                ? $data->resolve()
                : $data,
        ];

        if ($code !== null) {
            $payload['code'] = $code;
        }

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return new JsonResponse($payload, $status);
    }
}
