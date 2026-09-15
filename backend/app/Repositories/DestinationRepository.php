<?php

namespace App\Repositories;

use App\Models\Destination;
use App\Support\CrudList;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DestinationRepository
{
    /**
     * @return LengthAwarePaginator<int, Destination>
     */
    public function paginateForList(string $search, int $perPage, string $sort = '', string $dir = 'asc'): LengthAwarePaginator
    {
        return $this->filteredQuery($search, $sort, $dir)->paginate($perPage);
    }

    /**
     * @return Collection<int, Destination>
     */
    public function getForList(string $search, string $sort = '', string $dir = 'asc'): Collection
    {
        return $this->filteredQuery($search, $sort, $dir)->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Destination
    {
        return Destination::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(Destination $destination, array $attributes): Destination
    {
        $destination->fill($attributes);
        $destination->save();

        return $destination;
    }

    public function delete(Destination $destination): void
    {
        $destination->delete();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Destination>
     */
    private function filteredQuery(string $search, string $sort = '', string $dir = 'asc')
    {
        [$column, $direction] = CrudList::order($sort, $dir, ['dstcde', 'dstdsc'], 'dstcde');
        $query = Destination::query()->api()->orderBy($column, $direction);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('dstcde', 'like', $like)
                    ->orWhere('dstdsc', 'like', $like);
            });
        }

        return $query;
    }
}
