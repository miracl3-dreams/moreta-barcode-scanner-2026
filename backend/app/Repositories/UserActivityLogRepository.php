<?php

namespace App\Repositories;

use App\Models\UserActivityLog;
use App\Support\CrudList;
use DateTime;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class UserActivityLogRepository
{
    /**
     * @return LengthAwarePaginator<int, UserActivityLog>
     */
    public function paginateForList(string $search, int $perPage, string $sort = '', string $dir = 'asc'): LengthAwarePaginator
    {
        return $this->filteredQuery($search, $sort, $dir)->paginate($perPage);
    }

    /**
     * @return Collection<int, UserActivityLog>
     */
    public function getForList(string $search, string $sort = '', string $dir = 'asc'): Collection
    {
        return $this->filteredQuery($search, $sort, $dir)->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<UserActivityLog>
     */
    private function filteredQuery(string $search, string $sort = '', string $dir = 'asc')
    {
        [$column, $direction] = CrudList::order(
            $sort,
            $dir,
            ['usrdte', 'usrtim', 'usrcde', 'activity', 'remarks'],
            'usrdte',
        );
        $query = UserActivityLog::query()->api()->orderBy($column, $direction);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like, $search) {
                $inner->where('usrcde', 'like', $like)
                    ->orWhere('activity', 'like', $like)
                    ->orWhere('remarks', 'like', $like)
                    ->orWhere('usrdte', 'like', $like)
                    ->orWhere('usrtim', 'like', $like);

                $parsed = DateTime::createFromFormat('m-d-Y', $search);
                if ($parsed instanceof DateTime && $parsed->format('m-d-Y') === $search) {
                    $inner->orWhere('usrdte', $parsed->format('Y-m-d'));
                }
            });
        }

        return $query;
    }
}
