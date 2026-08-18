<?php

namespace App\Repositories\Contracts;

use App\Models\Tag;
use App\Models\Workspace;

interface TagRepositoryInterface extends RepositoryInterface
{
    public function findOrCreate(Workspace $workspace, string $name): Tag;
}
