<?php

namespace App\Repositories\Eloquent;

use App\Models\TaskComment;
use App\Repositories\Contracts\TaskCommentRepositoryInterface;

class TaskCommentRepository extends BaseRepository implements TaskCommentRepositoryInterface
{
    public function __construct(TaskComment $model)
    {
        parent::__construct($model);
    }
}
