<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\AppNotification;
use App\Models\User;
use App\Repositories\Contracts\AppNotificationRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationService
{
    public function __construct(private readonly AppNotificationRepositoryInterface $notifications) {}

    public function notify(User $user, NotificationType $type, array $payload): AppNotification
    {
        return $this->notifications->create([
            'user_id' => $user->id,
            'type' => $type->value,
            'payload' => $payload,
        ]);
    }

    public function listForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->notifications->forUser($user, $perPage);
    }

    public function unreadCount(User $user): int
    {
        return $this->notifications->unreadCountForUser($user);
    }

    public function markRead(AppNotification $notification): void
    {
        $notification->markAsRead();
    }

    public function markAllRead(User $user): void
    {
        $this->notifications->markAllReadForUser($user);
    }
}
