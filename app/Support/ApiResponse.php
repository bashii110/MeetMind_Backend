<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Shared response shaping so every endpoint returns the same envelope:
 *
 *   Success: { "data": {...}, "message": "..." }
 *   Error:   { "message": "...", "errors": {...} }
 *
 * Used by controllers instead of returning raw arrays/models, per
 * ARCHITECTURE.md section 5 (API Design Principles).
 */
trait ApiResponse
{
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        $payload = ['message' => $message];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    protected function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $payload = ['message' => $message];

        if (! empty($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
