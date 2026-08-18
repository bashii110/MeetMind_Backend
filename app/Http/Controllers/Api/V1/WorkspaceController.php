<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkspaceResource;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Minimal for now — just enough for the Flutter client to know which
 * workspace_id to attach meetings to. Full management (invites, roles,
 * departments) is Phase 7, per PHASES.md.
 */
class WorkspaceController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly WorkspaceRepositoryInterface $workspaces) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success(WorkspaceResource::collection($this->workspaces->forUser($request->user())));
    }
}
