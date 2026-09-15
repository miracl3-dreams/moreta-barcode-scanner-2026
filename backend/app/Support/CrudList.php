<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CrudList
{
    /**
     * @return int|'all'
     */
    public static function perPage(mixed $perPage): int|string
    {
        if ((string) $perPage === 'all') {
            return 'all';
        }

        $value = (int) $perPage;

        return in_array($value, [5, 10, 25, 50], true) ? $value : 5;
    }

    /**
     * YUI pager sort: whitelist the clicked list column, otherwise the first query field ASC.
     *
     * @param  list<string>  $allowed
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    public static function order(mixed $sort, mixed $dir, array $allowed, string $default, string $defaultDir = 'asc'): array
    {
        $requested = is_string($sort) && in_array($sort, $allowed, true);
        $column = $requested ? $sort : $default;
        if (! $requested) {
            $direction = strtolower($defaultDir) === 'desc' ? 'desc' : 'asc';
        } else {
            $direction = is_string($dir) && strtolower($dir) === 'desc' ? 'desc' : 'asc';
        }

        return [$column, $direction];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{data: mixed, meta: array<string, int>}
     */
    public static function fromCollection(Collection $rows): array
    {
        $data = $rows->map(fn ($row) => $row->toListArray())->values();
        $count = $data->count();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $count,
                'total' => $count,
            ],
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, object>  $paginator
     * @return array{data: mixed, meta: array<string, int>}
     */
    public static function fromPaginator(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->getCollection()->map(fn ($row) => $row->toListArray())->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => max(1, $paginator->lastPage()),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
