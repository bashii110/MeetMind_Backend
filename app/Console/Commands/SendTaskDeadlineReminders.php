<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendTaskDeadlineReminders extends Command
{
    protected $signature = 'tasks:send-reminders';

    protected $description = 'Notify assignees of tasks with a deadline in the next 24 hours (FR-7.4)';

    public function handle(TaskRepositoryInterface $tasks, NotificationService $notifications): int
    {
        $due = $tasks->dueForReminder();

        foreach ($due as $task) {
            $notifications->notify($task->assignee, NotificationType::Deadline, [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'deadline' => $task->deadline->toIso8601String(),
            ]);

            $task->update(['last_reminder_sent_at' => now()]);
        }

        $this->info("Sent {$due->count()} deadline reminder(s).");

        return self::SUCCESS;
    }
}
