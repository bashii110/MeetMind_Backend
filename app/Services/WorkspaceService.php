<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\NotificationType;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use Illuminate\Validation\ValidationException;

class WorkspaceService
{
    public function __construct(
        private readonly WorkspaceRepositoryInterface $workspaces,
        private readonly ActivityLogService $activity,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Every user gets a personal workspace on signup so they can create
     * meetings immediately, without a workspace-creation flow that
     * didn't exist until this phase. Named after the user for now;
     * they can rename it via update() once they have a use for that.
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

    /** FR-10.1: users can also create additional (team) workspaces explicitly. */
    public function create(User $owner, string $name): Workspace
    {
        $workspace = $this->workspaces->create(['name' => $name, 'owner_id' => $owner->id]);

        $workspace->members()->attach($owner->id, ['role' => WorkspaceRole::Owner->value]);

        return $workspace;
    }

    public function update(Workspace $workspace, string $name): Workspace
    {
        return $this->workspaces->update($workspace, ['name' => $name]);
    }

    /** Cascades to the workspace's meetings/tasks/tags/members via FK constraints. */
    public function delete(Workspace $workspace): void
    {
        $this->workspaces->delete($workspace);
    }

    /**
     * FR-10.2: adds an existing user to the workspace with the given
     * role. Deliberately adds them immediately rather than creating a
     * pending invitation (unlike meeting participants) — a workspace
     * admin adding a teammate is closer to "granting access" than
     * "requesting a response," and a single step avoids a whole second
     * state machine for a feature that doesn't call for one. The invitee
     * is still notified (NotificationType::WorkspaceInvitation) so it
     * doesn't happen silently.
     *
     * @throws ValidationException if no user has that email, or they're already a member
     */
    public function inviteMember(Workspace $workspace, User $invitedBy, string $email, WorkspaceRole $role): User
    {
        $invitee = User::query()->where('email', $email)->first();

        if (! $invitee) {
            throw ValidationException::withMessages(['email' => ['No account found for that email.']]);
        }

        if ($workspace->members()->where('users.id', $invitee->id)->exists()) {
            throw ValidationException::withMessages(['email' => ['That person is already a member of this workspace.']]);
        }

        $workspace->members()->attach($invitee->id, ['role' => $role->value]);

        $this->activity->log($workspace, $invitedBy, ActivityAction::MemberInvited, $invitee, [
            'member_name' => $invitee->name,
            'role' => $role->value,
        ]);

        $this->notifications->notify($invitee, NotificationType::WorkspaceInvitation, [
            'workspace_id' => $workspace->id,
            'workspace_name' => $workspace->name,
            'invited_by_name' => $invitedBy->name,
        ]);

        return $invitee;
    }

    public function updateMemberRole(Workspace $workspace, User $actor, User $member, WorkspaceRole $role): void
    {
        if ($member->id === $workspace->owner_id) {
            throw ValidationException::withMessages(['role' => ["The workspace owner's role can't be changed."]]);
        }

        $workspace->members()->updateExistingPivot($member->id, ['role' => $role->value]);

        $this->activity->log($workspace, $actor, ActivityAction::MemberRoleChanged, $member, [
            'member_name' => $member->name,
            'role' => $role->value,
        ]);
    }

    public function removeMember(Workspace $workspace, User $actor, User $member): void
    {
        if ($member->id === $workspace->owner_id) {
            throw ValidationException::withMessages(['member' => ['The workspace owner cannot be removed.']]);
        }

        $workspace->members()->detach($member->id);

        $this->activity->log($workspace, $actor, ActivityAction::MemberRemoved, $member, [
            'member_name' => $member->name,
        ]);
    }

    public function leave(Workspace $workspace, User $user): void
    {
        if ($user->id === $workspace->owner_id) {
            throw ValidationException::withMessages([
                'workspace' => ['The owner cannot leave their own workspace — delete it or transfer ownership instead.'],
            ]);
        }

        $workspace->members()->detach($user->id);
    }
}
