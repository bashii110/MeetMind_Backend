<?php

namespace App\Repositories\Eloquent;

use App\Models\Workspace;
use App\Models\WorkspaceFile;
use App\Repositories\Contracts\WorkspaceFileRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class WorkspaceFileRepository extends BaseRepository implements WorkspaceFileRepositoryInterface
{
    public function __construct(WorkspaceFile $model)
    {
        parent::__construct($model);
    }

    public function forWorkspace(Workspace $workspace): Collection
    {
        return $workspace->files()->with('uploadedBy')->get();
    }
}
