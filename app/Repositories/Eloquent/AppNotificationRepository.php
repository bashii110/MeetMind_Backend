<?php

namespace App\Repositories\Eloquent;

use App\Models\AppNotification;
use App\Models\User;
use App\Repositories\Contracts\AppNotificationRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class AppNotificationRepository extends BaseRepository implements AppNotificationRepositoryInterface
{
    public function __construct(AppNotification $model)
    {
        parent::__construct($model);
    }

    public function forUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $user->appNotifications()->latest()->paginate($perPage);
    }

    public function unreadCountForUser(User $user): int
    {
        return $user->appNotifications()->whereNull('read_at')->count();
    }

    public function markAllReadForUser(User $user): void
    {
        $user->appNotifications()->whereNull('read_at')->update(['read_at' => now()]);
    }
}
