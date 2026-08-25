<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TaskRepositoryInterface extends RepositoryInterface
{
    /**
     * @param array{status?: string, priority?: string, meeting_id?: int, assigned_to_me?: bool, search?: string} $filters
     */
    public function forUser(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Tasks with a deadline in the reminder window that haven't already
     * been reminded about — used by the scheduled reminder command.
     */
    public function dueForReminder(): Collection;

    /**
     * Unpaginated tasks whose deadline falls within [start, end], across
     * all the user's workspaces — backs the Calendar screen (FR-8.1/8.2).
     */
    public function forUserWithDeadlineBetween(User $user, CarbonInterface $start, CarbonInterface $end): Collection;
}
