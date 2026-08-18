<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\TaskAssigned;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreateTaskAssignedNotification implements ShouldQueue
{
    public $queue = 'notifications';

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TaskAssigned $event): void
    {
        $assignee = $event->task->assignee;

        // Don't notify someone for assigning a task to themselves.
        if (! $assignee || $assignee->id === $event->assignedBy->id) {
            return;
        }

        $this->notifications->notify($assignee, NotificationType::TaskAssigned, [
            'task_id' => $event->task->id,
            'task_title' => $event->task->title,
            'assigned_by_id' => $event->assignedBy->id,
            'assigned_by_name' => $event->assignedBy->name,
        ]);
    }
}
