<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Single choke point for the workspace activity timeline (DESIGN.md
 * 3.10) — the same shape as NotificationService::notify(). Callers pass
 * a workspace, an actor, an action, and an optional subject/metadata,
 * and don't need to know anything about how the timeline is stored.
 *
 * Deliberately called directly from MeetingService/TaskService/
 * WorkspaceService rather than via Events+Listeners: unlike
 * notifications (which have real decoupled side effects — push, email),
 * "record what happened" has exactly one consumer today, so the extra
 * indirection wouldn't buy anything yet.
 */
class ActivityLogService
{
    public function __construct(private readonly ActivityLogRepositoryInterface $activityLogs) {}

    /**
     * @param array<string, mixed> $metadata
     */
    public function log(Workspace $workspace, ?User $actor, ActivityAction $action, ?Model $subject = null, array $metadata = []): void
    {
        $this->activityLogs->create([
            'workspace_id' => $workspace->id,
            'user_id' => $actor?->id,
            'action' => $action->value,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
        ]);
    }

    public function forWorkspace(Workspace $workspace, int $perPage = 30): LengthAwarePaginator
    {
        return $this->activityLogs->forWorkspace($workspace, $perPage);
    }
}
