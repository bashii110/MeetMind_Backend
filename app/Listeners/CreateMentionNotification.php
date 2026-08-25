<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\CommentMentioned;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreateMentionNotification implements ShouldQueue
{
    public $queue = 'notifications';

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(CommentMentioned $event): void
    {
        // Don't notify someone for mentioning themselves.
        if ($event->mentionedUser->id === $event->mentionedBy->id) {
            return;
        }

        $this->notifications->notify($event->mentionedUser, NotificationType::Mention, [
            'task_id' => $event->task->id,
            'task_title' => $event->task->title,
            'comment_id' => $event->comment->id,
            'mentioned_by_id' => $event->mentionedBy->id,
            'mentioned_by_name' => $event->mentionedBy->name,
        ]);
    }
}
