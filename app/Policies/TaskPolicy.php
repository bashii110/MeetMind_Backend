<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return $this->isWorkspaceMember($user, $task);
    }

    public function update(User $user, Task $task): bool
    {
        return $task->created_by === $user->id
            || $task->assigned_user_id === $user->id
            || $this->isWorkspaceManager($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $task->created_by === $user->id || $this->isWorkspaceManager($user, $task);
    }

    public function assign(User $user, Task $task): bool
    {
        return $task->created_by === $user->id || $this->isWorkspaceManager($user, $task);
    }

    private function isWorkspaceMember(User $user, Task $task): bool
    {
        return $task->workspace->members()->where('users.id', $user->id)->exists();
    }

    private function isWorkspaceManager(User $user, Task $task): bool
    {
        $membership = $task->workspace->members()->where('users.id', $user->id)->first();

        if (! $membership) {
            return false;
        }

        return WorkspaceRole::from($membership->pivot->role)->isManager();
    }
}
