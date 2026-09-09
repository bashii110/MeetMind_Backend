<?php

namespace App\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface ReportedContentRepositoryInterface extends RepositoryInterface
{
    /**
     * @param array{status?: string} $filters
     */
    public function paginateFiltered(array $filters, int $perPage = 20): LengthAwarePaginator;
}
