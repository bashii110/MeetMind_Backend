<?php

namespace App\Repositories\Eloquent;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TaskRepository extends BaseRepository implements TaskRepositoryInterface
{
    public function __construct(Task $model)
    {
        parent::__construct($model);
    }

    public function forUser(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $workspaceIds = $user->workspaces()->pluck('workspaces.id');

        /** @var Builder $query */
        $query = Task::query()
            ->whereIn('workspace_id', $workspaceIds)
            ->with(['assignee', 'creator', 'meeting'])
            ->withCount('comments');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['meeting_id'])) {
            $query->where('meeting_id', $filters['meeting_id']);
        }

        if (! empty($filters['assigned_to_me'])) {
            $query->where('assigned_user_id', $user->id);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn (Builder $q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }

        return $query->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->orderBy('deadline')
            ->paginate($perPage);
    }

    public function dueForReminder(): Collection
    {
        return Task::query()
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])
            ->whereNotNull('deadline')
            ->whereNotNull('assigned_user_id')
            ->whereBetween('deadline', [now(), now()->addHours(24)])
            ->where(function (Builder $q) {
                $q->whereNull('last_reminder_sent_at')
                    ->orWhere('last_reminder_sent_at', '<', now()->subHours(24));
            })
            ->get();
    }
}
