<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sync\SyncRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SyncService;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

/** FR-15.2: the reconnect-time delta sync for meetings/tasks. */
class SyncController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SyncService $sync) {}

    public function index(SyncRequest $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request->user(), $request->integer('workspace_id') ?: null);

        if (! $workspace) {
            return $this->error('You do not belong to that workspace.', 403);
        }

        $since = $request->filled('since')
            ? Carbon::parse($request->string('since')->toString())
            : Carbon::createFromTimestamp(0); // no cursor yet — a fresh install wants everything

        return $this->success($this->sync->forWorkspace($workspace, $since));
    }

    private function resolveWorkspace(User $user, ?int $workspaceId): ?Workspace
    {
        if ($workspaceId) {
            return $user->workspaces()->where('workspaces.id', $workspaceId)->first();
        }

        return $user->workspaces()->first();
    }
}
