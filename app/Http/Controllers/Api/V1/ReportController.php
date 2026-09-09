<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreReportRequest;
use App\Http\Resources\ReportedContentResource;
use App\Services\ModerationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/** FR-16.3: any authenticated user can report content into the moderation queue. */
class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ModerationService $moderation) {}

    public function store(StoreReportRequest $request): JsonResponse
    {
        $report = $this->moderation->report($request->user(), $request->validated());

        return $this->success(new ReportedContentResource($report), 'Report submitted.', 201);
    }
}
