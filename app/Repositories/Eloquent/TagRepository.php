<?php

namespace App\Repositories\Eloquent;

use App\Models\Tag;
use App\Models\Workspace;
use App\Repositories\Contracts\TagRepositoryInterface;

class TagRepository extends BaseRepository implements TagRepositoryInterface
{
    public function __construct(Tag $model)
    {
        parent::__construct($model);
    }

    public function findOrCreate(Workspace $workspace, string $name): Tag
    {
        return Tag::query()->firstOrCreate([
            'workspace_id' => $workspace->id,
            'name' => trim($name),
        ]);
    }
}
