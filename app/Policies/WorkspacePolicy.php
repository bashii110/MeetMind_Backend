<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->members()->where('users.id', $user->id)->exists();
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $this->isManager($user, $workspace);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $this->isManager($user, $workspace);
    }

    private function isManager(User $user, Workspace $workspace): bool
    {
        $membership = $workspace->members()->where('users.id', $user->id)->first();

        if (! $membership) {
            return false;
        }

        return WorkspaceRole::from($membership->pivot->role)->isManager();
    }
}
