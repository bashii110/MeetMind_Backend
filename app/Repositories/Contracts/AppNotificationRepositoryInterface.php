<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface AppNotificationRepositoryInterface extends RepositoryInterface
{
    public function forUser(User $user, int $perPage = 20): LengthAwarePaginator;

    public function unreadCountForUser(User $user): int;

    public function markAllReadForUser(User $user): void;
}
