<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Phase 0 deliverable: proves the Flutter client can reach the Laravel API.
 * GET /api/v1/ping
 */
class PingController extends Controller
{
    use ApiResponse;

    public function __invoke(): JsonResponse
    {
        return $this->success([
            'app' => config('app.name'),
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ], 'pong');
    }
}
