<?php

namespace App\Repositories\Eloquent;

use App\Models\ActivityLog;
use App\Models\Workspace;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ActivityLogRepository extends BaseRepository implements ActivityLogRepositoryInterface
{
    public function __construct(ActivityLog $model)
    {
        parent::__construct($model);
    }

    public function forWorkspace(Workspace $workspace, int $perPage = 30): LengthAwarePaginator
    {
        return $workspace->activityLogs()->with('user')->paginate($perPage);
    }
}
