<?php

namespace App\Repositories\Contracts;

use App\Models\User;
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
}
