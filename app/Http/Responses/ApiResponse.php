<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The one response shape for /api/v1 (Phase 4).
 *
 *   { "success": true,  "message": "…", "data": {…}, "meta": {…}? }
 *   { "success": false, "message": "…", "errors": {…}? , "meta": {…}? }
 *
 * Error responses never include stack traces, SQL or exception class names.
 */
class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200, array $meta = []): JsonResponse
    {
        if ($data instanceof JsonResource) {
            $data = $data->resolve(request());
        }

        $body = ['success' => true, 'message' => $message, 'data' => $data];
        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status);
    }

    public static function error(string $message, int $status, array $errors = [], array $meta = [], array $headers = []): JsonResponse
    {
        $body = ['success' => false, 'message' => $message];
        if ($errors !== []) {
            $body['errors'] = $errors;
        }
        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status, $headers);
    }
}
