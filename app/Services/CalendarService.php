<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\MeetingRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Carbon\CarbonInterface;

/**
 * Backs the Calendar screen (FR-8.1/8.2, DESIGN.md 3.8: "Color-coded
 * dots for meetings vs. task deadlines. Tapping a day reveals a bottom
 * sheet with that day's agenda."). Kept as its own read-only service
 * rather than folded into MeetingService/TaskService, since it's a
 * cross-resource view that doesn't own either resource's lifecycle.
 */
class CalendarService
{
    public function __construct(
        private readonly MeetingRepositoryInterface $meetings,
        private readonly TaskRepositoryInterface $tasks,
    ) {}

    /**
     * @return array{meetings: array<int, array<string, mixed>>, task_deadlines: array<int, array<string, mixed>>}
     */
    public function forUser(User $user, CarbonInterface $start, CarbonInterface $end): array
    {
        $meetings = $this->meetings->forUserBetweenDates($user, $start, $end);
        $taskDeadlines = $this->tasks->forUserWithDeadlineBetween($user, $start, $end);

        return [
            'meetings' => $meetings->map(static fn ($meeting) => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'date' => $meeting->date?->toDateString(),
                'time' => $meeting->time,
                'status' => $meeting->status?->value,
                'priority' => $meeting->priority?->value,
            ])->values()->all(),

            'task_deadlines' => $taskDeadlines->map(static fn ($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'deadline' => $task->deadline?->toIso8601String(),
                'status' => $task->status?->value,
                'priority' => $task->priority?->value,
                'is_overdue' => $task->isOverdue(),
            ])->values()->all(),
        ];
    }
}
