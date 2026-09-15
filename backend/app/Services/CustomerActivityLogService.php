<?php

namespace App\Services;

use App\Repositories\CustomerActivityLogRepository;
use App\Support\CrudList;

class CustomerActivityLogService
{
    public function __construct(private CustomerActivityLogRepository $logs) {}

    /**
     * @return array{data: mixed, meta: array<string, int>}
     */
    public function list(string $search, mixed $perPage, string $sort = '', string $dir = 'asc'): array
    {
        $pageSize = CrudList::perPage($perPage);
        if ($pageSize === 'all') {
            return CrudList::fromCollection($this->logs->getForList($search, $sort, $dir));
        }

        return CrudList::fromPaginator($this->logs->paginateForList($search, $pageSize, $sort, $dir));
    }
}
