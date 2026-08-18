<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class WorkspaceRepository extends BaseRepository implements WorkspaceRepositoryInterface
{
    public function __construct(Workspace $model)
    {
        parent::__construct($model);
    }

    public function forUser(User $user): Collection
    {
        return $user->workspaces()->get();
    }
}
