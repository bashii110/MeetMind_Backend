<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/** FR-16.2: platform-wide stats for the Admin Dashboard's landing view. */
class AdminController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AdminService $admin) {}

    public function overview(): JsonResponse
    {
        return $this->success($this->admin->overview());
    }
}
