<?php

namespace App\Repositories\Contracts;

use App\Models\Workspace;
use Illuminate\Pagination\LengthAwarePaginator;

interface ActivityLogRepositoryInterface extends RepositoryInterface
{
    public function forWorkspace(Workspace $workspace, int $perPage = 30): LengthAwarePaginator;
}
