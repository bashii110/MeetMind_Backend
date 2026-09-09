<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\AudioFile;
use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;

/**
 * FR-13.1 / DESIGN.md 3.11: "Card-based chart grid: meetings/month (bar),
 * task completion (donut), avg. meeting duration (line), most active
 * users (leaderboard list)." Results are cached per workspace (
 * ARCHITECTURE.md's "Redis cache for analytics aggregates") with a short
 * TTL rather than invalidated on every write — a few minutes of staleness
 * on a dashboard chart is an acceptable trade-off for not having to hook
 * cache-busting into every meeting/task mutation across the codebase.
 *
 * Month bucketing is done in PHP with Carbon, not a raw SQL DATE_FORMAT/
 * strftime expression — the same portability reasoning already applied in
 * MeetingRepository::dueForReminder and CalendarService, since the test
 * suite runs against SQLite while production targets MySQL.
 */
class AnalyticsService
{
    private const CACHE_TTL_MINUTES = 10;

    /**
     * @return array{
     *   meetings_per_month: array<int, array{month: string, count: int}>,
     *   task_completion: array{pending: int, in_progress: int, completed: int, cancelled: int},
     *   avg_meeting_duration_minutes: ?float,
     *   active_users: array<int, array{user_id: int, name: ?string, avatar: ?string, activity_count: int}>,
     *   pending_tasks: int,
     *   total_meetings: int,
     * }
     */
    public function forWorkspace(Workspace $workspace): array
    {
        return Cache::remember(
            "analytics:workspace:{$workspace->id}",
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($workspace) {
                return [
                    'meetings_per_month' => $this->meetingsPerMonth($workspace),
                    'task_completion' => $this->taskCompletion($workspace),
                    'avg_meeting_duration_minutes' => $this->avgMeetingDurationMinutes($workspace),
                    'active_users' => $this->activeUsersLeaderboard($workspace),
                    'pending_tasks' => $workspace->tasks()->where('status', TaskStatus::Pending->value)->count(),
                    'total_meetings' => $workspace->meetings()->count(),
                ];
            },
        );
    }

    /**
     * @return array<int, array{month: string, count: int}>
     */
    public function meetingsPerMonth(Workspace $workspace, int $months = 6): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();

        $meetings = $workspace->meetings()
            ->where('date', '>=', $start->toDateString())
            ->get(['date']);

        $buckets = [];
        for ($i = 0; $i < $months; $i++) {
            $period = $start->copy()->addMonths($i);
            $buckets[$period->format('Y-m')] = ['month' => $period->format('M Y'), 'count' => 0];
        }

        foreach ($meetings as $meeting) {
            $key = $meeting->date->format('Y-m');
            if (isset($buckets[$key])) {
                $buckets[$key]['count']++;
            }
        }

        return array_values($buckets);
    }

    /**
     * @return array{pending: int, in_progress: int, completed: int, cancelled: int}
     */
    public function taskCompletion(Workspace $workspace): array
    {
        $counts = $workspace->tasks()
            ->selectRaw('status, count(*) as aggregate_count')
            ->groupBy('status')
            ->pluck('aggregate_count', 'status');

        return [
            'pending' => (int) ($counts['pending'] ?? 0),
            'in_progress' => (int) ($counts['in_progress'] ?? 0),
            'completed' => (int) ($counts['completed'] ?? 0),
            'cancelled' => (int) ($counts['cancelled'] ?? 0),
        ];
    }

    public function avgMeetingDurationMinutes(Workspace $workspace): ?float
    {
        $avgSeconds = AudioFile::query()
            ->whereHas('meeting', fn ($q) => $q->where('workspace_id', $workspace->id))
            ->whereNotNull('duration_seconds')
            ->avg('duration_seconds');

        return $avgSeconds === null ? null : round(((float) $avgSeconds) / 60, 1);
    }

    /**
     * @return array<int, array{user_id: int, name: ?string, avatar: ?string, activity_count: int}>
     */
    public function activeUsersLeaderboard(Workspace $workspace, int $limit = 5): array
    {
        $rows = ActivityLog::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, count(*) as aggregate_count')
            ->groupBy('user_id')
            ->orderByDesc('aggregate_count')
            ->limit($limit)
            ->get()
            ->load('user');

        return $rows->map(fn (ActivityLog $row) => [
            'user_id' => $row->user_id,
            'name' => $row->user?->name,
            'avatar' => $row->user?->avatar,
            'activity_count' => (int) $row->aggregate_count,
        ])->values()->all();
    }
}
