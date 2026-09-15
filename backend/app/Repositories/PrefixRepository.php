<?php

namespace App\Repositories;

use App\Models\Prefix;
use App\Support\CrudList;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PrefixRepository
{
    /**
     * @return LengthAwarePaginator<int, Prefix>
     */
    public function paginateForList(string $search, int $perPage, string $sort = '', string $dir = 'asc'): LengthAwarePaginator
    {
        return $this->filteredQuery($search, $sort, $dir)->paginate($perPage);
    }

    /**
     * @return Collection<int, Prefix>
     */
    public function getForList(string $search, string $sort = '', string $dir = 'asc'): Collection
    {
        return $this->filteredQuery($search, $sort, $dir)->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Prefix
    {
        return Prefix::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(Prefix $prefix, array $attributes): Prefix
    {
        $prefix->fill($attributes);
        $prefix->save();

        return $prefix;
    }

    public function delete(Prefix $prefix): void
    {
        $prefix->delete();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Prefix>
     */
    private function filteredQuery(string $search, string $sort = '', string $dir = 'asc')
    {
        [$column, $direction] = CrudList::order($sort, $dir, ['prefix', 'description'], 'prefix');
        $query = Prefix::query()->api()->orderBy($column, $direction);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('prefix', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        return $query;
    }
}
