<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AnalyticsService;
use App\Services\ProductivityScoreService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** FR-13.1 (workspace analytics) and FR-2.5 (productivity score). */
class AnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly ProductivityScoreService $productivity,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request->user(), $request->integer('workspace_id') ?: null);

        if (! $workspace) {
            return $this->error('You do not belong to that workspace.', 403);
        }

        return $this->success($this->analytics->forWorkspace($workspace));
    }

    public function productivityScore(Request $request): JsonResponse
    {
        return $this->success($this->productivity->scoreForUser($request->user()));
    }

    private function resolveWorkspace(User $user, ?int $workspaceId): ?Workspace
    {
        if ($workspaceId) {
            return $user->workspaces()->where('workspaces.id', $workspaceId)->first();
        }

        // No workspace_id given — default to the user's first workspace,
        // matching MeetingController/TaskController's convention.
        return $user->workspaces()->first();
    }
}
