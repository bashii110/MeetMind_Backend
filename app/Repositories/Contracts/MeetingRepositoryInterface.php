<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface MeetingRepositoryInterface extends RepositoryInterface
{
    /**
     * Meetings the user can see (owns, or is an invited participant of),
     * across all their workspaces, with optional filters — SRD 3.4 list
     * screen: "filter chips (status, category, tag) + search bar".
     *
     * @param array{status?: string, category?: string, tag?: string, search?: string} $filters
     */
    public function forUser(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Unpaginated meetings within [start, end] (inclusive, by date) that
     * the user can see — backs the Calendar screen (FR-8.1/8.2).
     */
    public function forUserBetweenDates(User $user, CarbonInterface $start, CarbonInterface $end): Collection;

    /**
     * Scheduled meetings starting within the next hour that haven't
     * already been reminded about — used by meetings:send-reminders.
     */
    public function dueForReminder(): Collection;
}
