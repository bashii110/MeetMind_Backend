<?php

namespace App\Repositories\Eloquent;

use App\Models\AudioFile;
use App\Repositories\Contracts\AudioFileRepositoryInterface;

class AudioFileRepository extends BaseRepository implements AudioFileRepositoryInterface
{
    public function __construct(AudioFile $model)
    {
        parent::__construct($model);
    }
}
