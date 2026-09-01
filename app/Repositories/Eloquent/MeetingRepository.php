<?php

namespace App\Repositories\Eloquent;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Repositories\Contracts\MeetingRepositoryInterface;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MeetingRepository extends BaseRepository implements MeetingRepositoryInterface
{
    public function __construct(Meeting $model)
    {
        parent::__construct($model);
    }

    public function forUser(
    User $user,
    array $filters = [],
    int $perPage = 15
): LengthAwarePaginator {

    /** @var Builder $query */
    $query = Meeting::query()
        ->where(function (Builder $q) use ($user) {
            $q->where('owner_id', $user->id)
                ->orWhereHas('participants', function (Builder $p) use ($user) {
                    $p->where('user_id', $user->id)
                        ->where('invite_status', 'accepted');
                });
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
        $query->whereHas(
            'tags',
            fn (Builder $t) => $t->where('name', $filters['tag'])
        );
    }

    if (! empty($filters['search'])) {
        $search = $filters['search'];

        $query->where(function (Builder $q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    return $query
        ->orderByDesc('date')
        ->orderByDesc('time')
        ->paginate($perPage);
}

   public function forUserBetweenDates(
    User $user,
    CarbonInterface $start,
    CarbonInterface $end
): Collection {
    return Meeting::query()
        ->where(function (Builder $q) use ($user) {
            $q->where('owner_id', $user->id)
                ->orWhereHas('participants', function (Builder $p) use ($user) {
                    $p->where('user_id', $user->id)
                        ->where('invite_status', 'accepted');
                });
        })
        ->whereBetween('date', [
            $start->toDateString(),
            $end->toDateString(),
        ])
        ->orderBy('date')
        ->orderBy('time')
        ->get();
}

    public function dueForReminder(): Collection
    {
        $now = now();
        $windowEnd = now()->addHour();

        // date/time are separate columns, and combining them with a raw
        // SQL expression isn't portable across the MySQL (production) and
        // SQLite (test suite) drivers this app runs on — see
        // TaskRepository::dueForReminder for the same tradeoff. Narrow
        // with a cheap date-range query first, then do the precise
        // date+time comparison in PHP with Carbon.
        return Meeting::query()
            ->where('status', MeetingStatus::Scheduled->value)
            ->whereNotNull('time')
            ->whereDate('date', '>=', $now->toDateString())
            ->whereDate('date', '<=', $windowEnd->toDateString())
            ->where(function (Builder $q) {
                $q->whereNull('last_reminder_sent_at')
                    ->orWhere('last_reminder_sent_at', '<', now()->subHours(3));
            })
            ->get()
            ->filter(function (Meeting $meeting) use ($now, $windowEnd) {
                $startsAt = Carbon::parse($meeting->date->toDateString().' '.$meeting->time);

                return $startsAt->betweenIncluded($now, $windowEnd);
            })
            ->values();
    }
}
