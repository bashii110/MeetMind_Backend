<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\InviteWorkspaceMemberRequest;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceMemberRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\WorkspaceResource;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\ActivityLogService;
use App\Services\WorkspaceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FR-10.1/10.2/10.4. Member and activity-timeline management live here
 * as methods on this controller (not separate controllers), matching how
 * MeetingController owns its participants sub-resource — both are
 * inherently "manage who's in this thing" concerns of their parent
 * resource, not independent domains. ValidationException thrown by
 * WorkspaceService (e.g. "owner can't be removed") is left to
 * bootstrap/app.php's global handler rather than caught here, same as
 * AudioUploadService's validation failures.
 */
class WorkspaceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly WorkspaceRepositoryInterface $workspaces,
        private readonly WorkspaceService $workspaceService,
        private readonly ActivityLogService $activity,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success(WorkspaceResource::collection($this->workspaces->forUser($request->user())));
    }

    public function store(StoreWorkspaceRequest $request): JsonResponse
    {
        $workspace = $this->workspaceService->create($request->user(), $request->string('name')->toString());

        return $this->success(new WorkspaceResource($workspace), 'Workspace created.', 201);
    }

    public function show(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        $workspace->loadCount('members')->load(['members', 'departments']);

        return $this->success(new WorkspaceResource($workspace));
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('update', $workspace);

        $workspace = $this->workspaceService->update($workspace, $request->string('name')->toString());

        return $this->success(new WorkspaceResource($workspace), 'Workspace updated.');
    }

    public function destroy(Workspace $workspace): JsonResponse
    {
        $this->authorize('delete', $workspace);

        $this->workspaceService->delete($workspace);

        return $this->success(null, 'Workspace deleted.');
    }

    public function inviteMember(InviteWorkspaceMemberRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('manageMembers', $workspace);

        $this->workspaceService->inviteMember(
            $workspace,
            $request->user(),
            $request->string('email')->toString(),
            WorkspaceRole::from($request->input('role', WorkspaceRole::Member->value)),
        );

        return $this->success(null, 'Member added.');
    }

    public function updateMember(UpdateWorkspaceMemberRequest $request, Workspace $workspace, User $user): JsonResponse
    {
        $this->authorize('manageMembers', $workspace);

        if ($request->filled('role')) {
            $this->workspaceService->updateMemberRole(
                $workspace,
                $request->user(),
                $user,
                WorkspaceRole::from($request->string('role')->toString()),
            );
        }

        if ($request->has('department_id')) {
            $workspace->members()->updateExistingPivot($user->id, ['department_id' => $request->input('department_id')]);
        }

        return $this->success(null, 'Member updated.');
    }

    public function removeMember(Request $request, Workspace $workspace, User $user): JsonResponse
    {
        $this->authorize('manageMembers', $workspace);

        $this->workspaceService->removeMember($workspace, $request->user(), $user);

        return $this->success(null, 'Member removed.');
    }

    public function leave(Request $request, Workspace $workspace): JsonResponse
    {
        $this->workspaceService->leave($workspace, $request->user());

        return $this->success(null, 'You left the workspace.');
    }

    public function activity(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        $logs = $this->activity->forWorkspace($workspace);

        return $this->success([
            'items' => ActivityLogResource::collection($logs->items()),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
