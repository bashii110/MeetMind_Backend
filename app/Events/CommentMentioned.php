<?php

namespace App\Events;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentMentioned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Task $task,
        public readonly TaskComment $comment,
        public readonly User $mentionedUser,
        public readonly User $mentionedBy,
    ) {}
}
