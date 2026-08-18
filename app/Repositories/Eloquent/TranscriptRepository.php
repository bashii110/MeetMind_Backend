<?php

namespace App\Repositories\Eloquent;

use App\Models\Transcript;
use App\Repositories\Contracts\TranscriptRepositoryInterface;

class TranscriptRepository extends BaseRepository implements TranscriptRepositoryInterface
{
    public function __construct(Transcript $model)
    {
        parent::__construct($model);
    }
}
