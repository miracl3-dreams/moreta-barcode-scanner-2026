<?php

namespace App\Repositories;

use App\Models\Bank;
use App\Support\CrudList;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BankRepository
{
    /**
     * @return LengthAwarePaginator<int, Bank>
     */
    public function paginateForList(string $search, int $perPage, string $sort = '', string $dir = 'asc'): LengthAwarePaginator
    {
        return $this->filteredQuery($search, $sort, $dir)->paginate($perPage);
    }

    /**
     * @return Collection<int, Bank>
     */
    public function getForList(string $search, string $sort = '', string $dir = 'asc'): Collection
    {
        return $this->filteredQuery($search, $sort, $dir)->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Bank
    {
        return Bank::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(Bank $bank, array $attributes): Bank
    {
        $bank->fill($attributes);
        $bank->save();

        return $bank;
    }

    public function delete(Bank $bank): void
    {
        $bank->delete();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Bank>
     */
    private function filteredQuery(string $search, string $sort = '', string $dir = 'asc')
    {
        [$column, $direction] = CrudList::order($sort, $dir, ['bnkcde', 'bnkdsc'], 'bnkcde');
        $query = Bank::query()->api()->orderBy($column, $direction);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('bnkcde', 'like', $like)
                    ->orWhere('bnkdsc', 'like', $like);
            });
        }

        return $query;
    }
}
