<?php

namespace App\Repositories;

use App\Models\Consignee;
use App\Support\CrudList;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class ConsigneeRepository
{
    /**
     * @return LengthAwarePaginator<int, Consignee>
     */
    public function paginateForList(string $search, int $perPage, string $sort = '', string $dir = 'asc'): LengthAwarePaginator
    {
        return $this->filteredQuery($search, $sort, $dir)->paginate($perPage);
    }

    /**
     * @return Collection<int, Consignee>
     */
    public function getForList(string $search, string $sort = '', string $dir = 'asc'): Collection
    {
        return $this->filteredQuery($search, $sort, $dir)->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Consignee
    {
        return Consignee::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(Consignee $consignee, array $attributes): Consignee
    {
        $consignee->fill($attributes);
        $consignee->save();

        return $consignee;
    }

    public function delete(Consignee $consignee): void
    {
        $consignee->delete();
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
     * @return LazyCollection<int, Consignee>
     */
    public function printCursor(): LazyCollection
    {
        return Consignee::query()->select(['concde', 'condsc'])->orderBy('concde')->cursor();
    }

    public function existsByCodeOrName(string $concde, string $condsc): bool
    {
        return Consignee::query()
            ->where(function ($inner) use ($concde, $condsc) {
                $inner->where('concde', $concde)->orWhere('condsc', $condsc);
            })
            ->exists();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Consignee>
     */
    private function filteredQuery(string $search, string $sort = '', string $dir = 'asc')
    {
        [$column, $direction] = CrudList::order($sort, $dir, ['concde', 'condsc'], 'concde');
        $query = Consignee::query()->api()->orderBy($column, $direction);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('concde', 'like', $like)
                    ->orWhere('condsc', 'like', $like);
            });
        }

        return $query;
    }
}
