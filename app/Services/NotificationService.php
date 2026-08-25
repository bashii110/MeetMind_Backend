<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Jobs\SendPushNotificationJob;
use App\Models\AppNotification;
use App\Models\User;
use App\Repositories\Contracts\AppNotificationRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationService
{
    public function __construct(private readonly AppNotificationRepositoryInterface $notifications) {}

    /**
     * Every caller (meeting invites, task assignment/completion, deadline
     * and meeting reminders, mentions, workspace invites) goes through
     * this single method, so Phase 6's push delivery is wired in exactly
     * once here rather than at each call site.
     */
    public function notify(User $user, NotificationType $type, array $payload): AppNotification
    {
        $notification = $this->notifications->create([
            'user_id' => $user->id,
            'type' => $type->value,
            'payload' => $payload,
        ]);

        SendPushNotificationJob::dispatch($user, $type, $payload);

        return $notification;
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
