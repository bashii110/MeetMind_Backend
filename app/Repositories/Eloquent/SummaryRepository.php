<?php

namespace App\Repositories\Eloquent;

use App\Models\Summary;
use App\Repositories\Contracts\SummaryRepositoryInterface;

class SummaryRepository extends BaseRepository implements SummaryRepositoryInterface
{
    public function __construct(Summary $model)
    {
        parent::__construct($model);
    }
}
