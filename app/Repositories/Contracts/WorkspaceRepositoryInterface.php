<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface WorkspaceRepositoryInterface extends RepositoryInterface
{
    /** Workspaces the given user is a member of (any role). */
    public function forUser(User $user): Collection;
}
