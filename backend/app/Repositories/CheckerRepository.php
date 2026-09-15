<?php

namespace App\Repositories;

use App\Models\Checker;
use App\Support\CrudList;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CheckerRepository
{
    /**
     * @return LengthAwarePaginator<int, Checker>
     */
    public function paginateForList(string $search, int $perPage, string $sort = '', string $dir = 'asc'): LengthAwarePaginator
    {
        return $this->filteredQuery($search, $sort, $dir)->paginate($perPage);
    }

    /**
     * @return Collection<int, Checker>
     */
    public function getForList(string $search, string $sort = '', string $dir = 'asc'): Collection
    {
        return $this->filteredQuery($search, $sort, $dir)->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Checker
    {
        return Checker::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(Checker $checker, array $attributes): Checker
    {
        $checker->fill($attributes);
        $checker->save();

        return $checker;
    }

    public function delete(Checker $checker): void
    {
        $checker->delete();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Checker>
     */
    private function filteredQuery(string $search, string $sort = '', string $dir = 'asc')
    {
        [$column, $direction] = CrudList::order($sort, $dir, ['cuscde'], 'cuscde');
        $query = Checker::query()->api()->orderBy($column, $direction);

        if ($search !== '') {
            $query->where('cuscde', 'like', '%'.$search.'%');
        }

        return $query;
    }
}
