<?php

namespace App\Services;

use App\Enums\MeetingStatus;
use App\Enums\TaskStatus;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * FR-2.5 / DESIGN.md 3.3: "productivity score badge" on the dashboard
 * greeting header. Computed for a rolling period (defaults to the current
 * week) rather than all-time, so the score reflects recent behavior
 * rather than being dominated by history.
 *
 * The formula is intentionally simple and documented in full here rather
 * than tucked away in a magic-number soup, since it's the kind of thing a
 * reviewer will want to sanity-check:
 *
 *   +10 points  per task completed on/before its deadline (or with no deadline)
 *   +4  points  per task completed after its deadline
 *   -5  points  per task still open whose deadline has already passed
 *   +2  points  per completed meeting the user owned or attended (accepted invite)
 *
 * The raw total is clamped to [0, 100] so the badge always renders a
 * sane, comparable number regardless of how active a period was.
 */
class ProductivityScoreService
{
    /**
     * @return array{
     *   score: int,
     *   period_start: string,
     *   period_end: string,
     *   breakdown: array{completed_on_time: int, completed_late: int, overdue_open: int, meetings_attended: int},
     * }
     */
    public function scoreForUser(User $user, ?CarbonInterface $periodStart = null, ?CarbonInterface $periodEnd = null): array
    {
        $periodStart ??= now()->startOfWeek();
        $periodEnd ??= now()->endOfWeek();

        $completedOnTime = Task::query()
            ->where('assigned_user_id', $user->id)
            ->where('status', TaskStatus::Completed->value)
            ->whereBetween('updated_at', [$periodStart, $periodEnd])
            ->where(fn ($q) => $q->whereNull('deadline')->orWhereColumn('updated_at', '<=', 'deadline'))
            ->count();

        $completedLate = Task::query()
            ->where('assigned_user_id', $user->id)
            ->where('status', TaskStatus::Completed->value)
            ->whereBetween('updated_at', [$periodStart, $periodEnd])
            ->whereNotNull('deadline')
            ->whereColumn('updated_at', '>', 'deadline')
            ->count();

        $overdueOpen = Task::query()
            ->where('assigned_user_id', $user->id)
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])
            ->whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->count();

        $meetingsAttended = Meeting::query()
            ->where('status', MeetingStatus::Completed->value)
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->where(fn ($q) => $q
                ->where('owner_id', $user->id)
                ->orWhereHas('participants', fn ($p) => $p
                    ->where('user_id', $user->id)
                    ->where('invite_status', 'accepted')))
            ->count();

        $raw = ($completedOnTime * 10) + ($completedLate * 4) - ($overdueOpen * 5) + ($meetingsAttended * 2);

        return [
            'score' => max(0, min(100, $raw)),
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'breakdown' => [
                'completed_on_time' => $completedOnTime,
                'completed_late' => $completedLate,
                'overdue_open' => $overdueOpen,
                'meetings_attended' => $meetingsAttended,
            ],
        ];
    }
}
