<?php

namespace App\Repositories\Eloquent;

use App\Models\Meeting;
use App\Models\TaskCandidate;
use App\Repositories\Contracts\TaskCandidateRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TaskCandidateRepository extends BaseRepository implements TaskCandidateRepositoryInterface
{
    public function __construct(TaskCandidate $model)
    {
        parent::__construct($model);
    }

    public function forMeeting(Meeting $meeting): Collection
    {
        return $meeting->taskCandidates()->latest()->get();
    }
}
