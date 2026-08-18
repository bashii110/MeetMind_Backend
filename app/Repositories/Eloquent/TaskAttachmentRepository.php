<?php

namespace App\Repositories\Eloquent;

use App\Models\TaskAttachment;
use App\Repositories\Contracts\TaskAttachmentRepositoryInterface;

class TaskAttachmentRepository extends BaseRepository implements TaskAttachmentRepositoryInterface
{
    public function __construct(TaskAttachment $model)
    {
        parent::__construct($model);
    }
}
