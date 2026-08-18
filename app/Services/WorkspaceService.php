<?php

namespace App\Services;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;

class WorkspaceService
{
    public function __construct(private readonly WorkspaceRepositoryInterface $workspaces) {}

    /**
     * Every user gets a personal workspace on signup so they can create
     * meetings immediately, without a workspace-creation flow that doesn't
     * exist until Phase 7. Named after the user for now (e.g. "Ali's
     * Workspace"); users can rename it once workspace settings exist.
     */
    public function createPersonalWorkspace(User $user): Workspace
    {
        $workspace = $this->workspaces->create([
            'name' => "{$user->name}'s Workspace",
            'owner_id' => $user->id,
        ]);

        $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);

        return $workspace;
    }
}
