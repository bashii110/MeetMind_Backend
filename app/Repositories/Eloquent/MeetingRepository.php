<?php

namespace App\Repositories\Eloquent;

use App\Models\Meeting;
use App\Models\User;
use App\Repositories\Contracts\MeetingRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class MeetingRepository extends BaseRepository implements MeetingRepositoryInterface
{
    public function __construct(Meeting $model)
    {
        parent::__construct($model);
    }

    public function forUser(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $workspaceIds = $user->workspaces()->pluck('workspaces.id');

        /** @var Builder $query */
        $query = Meeting::query()
            ->whereIn('workspace_id', $workspaceIds)
            ->where(function (Builder $q) use ($user) {
                $q->where('owner_id', $user->id)
                    ->orWhereHas('participants', fn (Builder $p) => $p->where('user_id', $user->id));
            })
            ->with(['owner', 'tags', 'participants.user'])
            ->withCount('participants');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['tag'])) {
            $query->whereHas('tags', fn (Builder $t) => $t->where('name', $filters['tag']));
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn (Builder $q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }

        return $query->orderByDesc('date')->orderByDesc('time')->paginate($perPage);
    }
}
