<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWorkspaceFileRequest;
use App\Http\Resources\WorkspaceFileResource;
use App\Models\Workspace;
use App\Models\WorkspaceFile;
use App\Services\WorkspaceFileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** FR-10.5: shared files within a workspace. */
class WorkspaceFileController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly WorkspaceFileService $files) {}

    public function index(Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        return $this->success(WorkspaceFileResource::collection($this->files->listForWorkspace($workspace)));
    }

    public function store(StoreWorkspaceFileRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        $file = $this->files->upload($workspace, $request->user(), $request->file('file'));
        $file->load('uploadedBy');

        return $this->success(new WorkspaceFileResource($file), 'File uploaded.', 201);
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceFile $file): JsonResponse
    {
        abort_if($file->workspace_id !== $workspace->id, 404);

        // Either whoever uploaded it, or a workspace manager, can remove it.
        $isUploader = $file->uploaded_by === $request->user()->id;
        abort_unless($isUploader || $request->user()->can('manageMembers', $workspace), 403);

        $this->files->delete($file);

        return $this->success(null, 'File removed.');
    }
}
