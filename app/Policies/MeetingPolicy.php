<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function view(User $user, Meeting $meeting): bool
    {
        if ($meeting->owner_id === $user->id) {
            return true;
        }

        if ($meeting->participants()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return $this->isWorkspaceMember($user, $meeting);
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $meeting->owner_id === $user->id || $this->isWorkspaceManager($user, $meeting);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $this->update($user, $meeting);
    }

    public function manageParticipants(User $user, Meeting $meeting): bool
    {
        return $this->update($user, $meeting);
    }

    private function isWorkspaceMember(User $user, Meeting $meeting): bool
    {
        return $meeting->workspace->members()->where('users.id', $user->id)->exists();
    }

    private function isWorkspaceManager(User $user, Meeting $meeting): bool
    {
        $membership = $meeting->workspace->members()->where('users.id', $user->id)->first();

        if (! $membership) {
            return false;
        }

        $role = WorkspaceRole::from($membership->pivot->role);

        return $role->isManager();
    }
}
