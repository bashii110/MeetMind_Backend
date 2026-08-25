<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Models\Workspace;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * PHASES.md marks departments an optional sub-structure — kept
 * intentionally thin (no repository/service layer) rather than mirroring
 * the full pattern used for required resources like Workspace/Task.
 */
class DepartmentController extends Controller
{
    use ApiResponse;

    public function index(Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        return $this->success(DepartmentResource::collection($workspace->departments));
    }

    public function store(StoreDepartmentRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('manageMembers', $workspace);

        $department = $workspace->departments()->create(['name' => $request->string('name')->toString()]);

        return $this->success(new DepartmentResource($department), 'Department created.', 201);
    }

    public function destroy(Workspace $workspace, Department $department): JsonResponse
    {
        $this->authorize('manageMembers', $workspace);

        abort_if($department->workspace_id !== $workspace->id, 404);

        $department->delete();

        return $this->success(null, 'Department deleted.');
    }
}
