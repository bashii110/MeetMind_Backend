<?php

namespace App\Repositories\Contracts;

use App\Models\Meeting;
use Illuminate\Database\Eloquent\Collection;

interface TaskCandidateRepositoryInterface extends RepositoryInterface
{
    public function forMeeting(Meeting $meeting): Collection;
}
