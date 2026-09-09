<?php

namespace App\Repositories\Eloquent;

use App\Models\ReportedContent;
use App\Repositories\Contracts\ReportedContentRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ReportedContentRepository extends BaseRepository implements ReportedContentRepositoryInterface
{
    public function __construct(ReportedContent $model)
    {
        parent::__construct($model);
    }

    public function paginateFiltered(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        /** @var Builder $query */
        $query = ReportedContent::query()->with(['reporter', 'resolver'])->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }
}
