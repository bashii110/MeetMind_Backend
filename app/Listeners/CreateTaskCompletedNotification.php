<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\TaskCompleted;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreateTaskCompletedNotification implements ShouldQueue
{
    public $queue = 'notifications';

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TaskCompleted $event): void
    {
        $creator = $event->task->creator;

        // Don't notify someone for completing their own task.
        if (! $creator || $creator->id === $event->task->assigned_user_id) {
            return;
        }

        $this->notifications->notify($creator, NotificationType::TaskCompleted, [
            'task_id' => $event->task->id,
            'task_title' => $event->task->title,
        ]);
    }
}
