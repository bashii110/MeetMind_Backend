<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

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
}
