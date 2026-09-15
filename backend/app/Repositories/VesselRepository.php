<?php

namespace App\Repositories;

use App\Models\Vessel;
use App\Support\CrudList;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class VesselRepository
{
    /**
     * @return LengthAwarePaginator<int, Vessel>
     */
    public function paginateForList(string $search, int $perPage, string $sort = '', string $dir = 'asc'): LengthAwarePaginator
    {
        return $this->filteredQuery($search, $sort, $dir)->paginate($perPage);
    }

    /**
     * @return Collection<int, Vessel>
     */
    public function getForList(string $search, string $sort = '', string $dir = 'asc'): Collection
    {
        return $this->filteredQuery($search, $sort, $dir)->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Vessel
    {
        return Vessel::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(Vessel $vessel, array $attributes): Vessel
    {
        $vessel->fill($attributes);
        $vessel->save();

        return $vessel;
    }

    public function delete(Vessel $vessel): void
    {
        $vessel->delete();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Vessel>
     */
    private function filteredQuery(string $search, string $sort = '', string $dir = 'asc')
    {
        [$column, $direction] = CrudList::order($sort, $dir, ['vsslcde', 'vssldsc'], 'vsslcde');
        $query = Vessel::query()->api()->orderBy($column, $direction);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('vsslcde', 'like', $like)
                    ->orWhere('vssldsc', 'like', $like);
            });
        }

        return $query;
    }
}
