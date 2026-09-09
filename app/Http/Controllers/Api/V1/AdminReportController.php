<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContentReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModerateReportRequest;
use App\Http\Resources\ReportedContentResource;
use App\Models\ReportedContent;
use App\Services\ModerationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** FR-16.3: the admin side of the content-moderation queue. */
class AdminReportController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ModerationService $moderation) {}

    public function index(Request $request): JsonResponse
    {
        $reports = $this->moderation->listForAdmin(
            $request->only(['status']),
            (int) $request->integer('per_page', 20),
        );

        return $this->success([
            'items' => ReportedContentResource::collection($reports->items()),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    public function update(ModerateReportRequest $request, ReportedContent $report): JsonResponse
    {
        $report = $this->moderation->resolve(
            $report,
            $request->user(),
            ContentReportStatus::from($request->string('status')->toString()),
            $request->input('notes'),
        );

        return $this->success(new ReportedContentResource($report), 'Report updated.');
    }
}
