<?php

namespace App\Repositories;

use App\Models\EirForm;
use App\Support\CrudList;
use App\Support\LegacySchema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EirSigningRepository
{
    /**
     * @return LengthAwarePaginator<int, EirForm>
     */
    public function paginatePending(string $search, int $perPage, string $sort = '', string $dir = 'desc'): LengthAwarePaginator
    {
        [$column, $direction] = CrudList::order(
            $sort,
            $dir,
            ['docnum', 'issue_dte', 'vannum', 'trucker', 'origin'],
            'recid',
            'desc',
        );

        return $this->pendingQuery($search)->orderBy($column, $direction)->paginate($perPage);
    }

    /**
     * @return Collection<int, EirForm>
     */
    public function getPending(string $search, string $sort = '', string $dir = 'desc'): Collection
    {
        [$column, $direction] = CrudList::order(
            $sort,
            $dir,
            ['docnum', 'issue_dte', 'vannum', 'trucker', 'origin'],
            'recid',
            'desc',
        );

        return $this->pendingQuery($search)->orderBy($column, $direction)->get();
    }

    public function findByRecid(int $recid): ?EirForm
    {
        return EirForm::query()->api()->where('recid', $recid)->first();
    }

    public function lockByRecid(int $recid): ?EirForm
    {
        return EirForm::query()->api()->where('recid', $recid)->lockForUpdate()->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(EirForm $eir, array $attributes): EirForm
    {
        $eir->fill($attributes);
        $eir->save();

        return EirForm::query()->api()->where('recid', $eir->recid)->firstOrFail();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<EirForm>
     */
    private function pendingQuery(string $search)
    {
        // Same working set as the EIR transaction list: not yet accepted.
        // Legacy default for unsigned filenames is the text "NULL".
        $query = EirForm::query()->api()->unaccepted();
        if (LegacySchema::hasColumn('eirtranfile1', 'rep_driver_sign')) {
            $query->where(function ($inner) {
                $inner->whereNull('rep_driver_sign')
                    ->orWhereRaw("TRIM(COALESCE(rep_driver_sign, '')) = ''")
                    ->orWhereRaw("UPPER(TRIM(COALESCE(rep_driver_sign, ''))) = 'NULL'");
            });
        }

        $term = trim($search);
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('docnum', 'like', $like)
                    ->orWhere('vannum', 'like', $like)
                    ->orWhere('trucker', 'like', $like)
                    ->orWhere('origin', 'like', $like);
            });
        }

        return $query;
    }
}
