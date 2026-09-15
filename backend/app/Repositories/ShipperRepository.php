<?php

namespace App\Repositories;

use App\Models\Shipper;
use App\Support\CrudList;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class ShipperRepository
{
    /**
     * @return LengthAwarePaginator<int, Shipper>
     */
    public function paginateForList(string $search, int $perPage, string $sort = '', string $dir = 'asc'): LengthAwarePaginator
    {
        return $this->filteredQuery($search, $sort, $dir)->paginate($perPage);
    }

    /**
     * @return Collection<int, Shipper>
     */
    public function getForList(string $search, string $sort = '', string $dir = 'asc'): Collection
    {
        return $this->filteredQuery($search, $sort, $dir)->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Shipper
    {
        return Shipper::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(Shipper $shipper, array $attributes): Shipper
    {
        $shipper->fill($attributes);
        $shipper->save();

        return $shipper;
    }

    public function delete(Shipper $shipper): void
    {
        $shipper->delete();
    }

    public function companyName(): string
    {
        $row = DB::table('companyfile')->first();
        if (! $row) {
            return '';
        }
        if (isset($row->comdsc) && trim((string) $row->comdsc) !== '') {
            return (string) $row->comdsc;
        }

        return (string) ($row->companydescription ?? '');
    }

    /**
     * @return LazyCollection<int, Shipper>
     */
    public function printCursor(): LazyCollection
    {
        return Shipper::query()->select(['cuscde', 'cusdsc'])->orderBy('cuscde')->cursor();
    }

    public function existsByCodeOrName(string $cuscde, string $cusdsc): bool
    {
        return Shipper::query()
            ->where(function ($inner) use ($cuscde, $cusdsc) {
                $inner->where('cuscde', $cuscde)->orWhere('cusdsc', $cusdsc);
            })
            ->exists();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Shipper>
     */
    private function filteredQuery(string $search, string $sort = '', string $dir = 'asc')
    {
        [$column, $direction] = CrudList::order($sort, $dir, ['cuscde', 'cusdsc'], 'cuscde');
        $query = Shipper::query()->api()->orderBy($column, $direction);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('cuscde', 'like', $like)
                    ->orWhere('cusdsc', 'like', $like);
            });
        }

        return $query;
    }
}
