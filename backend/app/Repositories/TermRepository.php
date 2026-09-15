<?php

namespace App\Repositories;

use App\Models\Term;
use App\Support\CrudList;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TermRepository
{
    /**
     * @return LengthAwarePaginator<int, Term>
     */
    public function paginateForList(string $search, int $perPage, string $sort = '', string $dir = 'asc'): LengthAwarePaginator
    {
        return $this->filteredQuery($search, $sort, $dir)->paginate($perPage);
    }

    /**
     * @return Collection<int, Term>
     */
    public function getForList(string $search, string $sort = '', string $dir = 'asc'): Collection
    {
        return $this->filteredQuery($search, $sort, $dir)->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Term
    {
        return Term::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(Term $term, array $attributes): Term
    {
        $term->fill($attributes);
        $term->save();

        return $term;
    }

    public function delete(Term $term): void
    {
        $term->delete();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Term>
     */
    private function filteredQuery(string $search, string $sort = '', string $dir = 'asc')
    {
        [$column, $direction] = CrudList::order($sort, $dir, ['trmcde', 'trmdsc', 'trmday'], 'trmcde');
        $query = Term::query()->api()->orderBy($column, $direction);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('trmcde', 'like', $like)
                    ->orWhere('trmdsc', 'like', $like)
                    ->orWhere('trmday', 'like', $like);
            });
        }

        return $query;
    }
}
