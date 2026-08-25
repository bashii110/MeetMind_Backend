<?php

namespace App\Repositories\Contracts;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;

interface WorkspaceFileRepositoryInterface extends RepositoryInterface
{
    public function forWorkspace(Workspace $workspace): Collection;
}
