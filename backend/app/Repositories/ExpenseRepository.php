<?php

namespace App\Repositories;

use App\Models\Expense;
use App\Support\CrudList;
use App\Support\MenuPermission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExpenseRepository
{
    /**
     * @return LengthAwarePaginator<int, Expense>
     */
    public function paginateForList(string $search, int $perPage, string $sort = '', string $dir = 'asc'): LengthAwarePaginator
    {
        return $this->filteredQuery($search, $sort, $dir)->paginate($perPage);
    }

    /**
     * @return Collection<int, Expense>
     */
    public function getForList(string $search, string $sort = '', string $dir = 'asc'): Collection
    {
        return $this->filteredQuery($search, $sort, $dir)->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Expense
    {
        $expense = Expense::query()->create($attributes);

        return Expense::query()->api()->where('recid', $expense->recid)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(Expense $expense, array $attributes): Expense
    {
        $expense->fill($attributes);
        $expense->save();

        return Expense::query()->api()->where('recid', $expense->recid)->firstOrFail();
    }

    public function delete(Expense $expense): void
    {
        $expense->delete();
    }

    /**
     * Next expenses document number from syspar.arrdocnum (ajax_getdocnum.php xaction=expenses).
     */
    public function nextDocnum(): string
    {
        return (string) DB::transaction(function () {
            if (! Schema::hasTable('syspar') || ! Schema::hasColumn('syspar', 'arrdocnum')) {
                return $this->fallbackDocnum();
            }

            $row = DB::table('syspar')->lockForUpdate()->first();
            if ($row === null) {
                return $this->fallbackDocnum();
            }

            $current = trim((string) ($row->arrdocnum ?? ''));
            if ($current === '') {
                $current = 'EXP-00000001';
            }

            $next = $this->nextSeries($current);
            $update = ['arrdocnum' => $next];
            if (Schema::hasColumn('syspar', 'is_arrlock')) {
                $update['is_arrlock'] = 0;
            }
            DB::table('syspar')->update($update);

            return $current;
        });
    }

    /**
     * @return object{shiftcde: mixed, shiftdate: mixed, branchcde: mixed}|null
     */
    public function branchShift(string $branch): ?object
    {
        if (! Schema::hasTable('shift_branchfile') || $branch === '') {
            return null;
        }

        return DB::table('shift_branchfile')->where('branchcde', $branch)->first();
    }

    /**
     * @return array{allow_add: bool, allow_edit: bool, allow_delete: bool, allow_view: bool, allow_print: bool}|null
     */
    public function menuPermissions(string $usrcde): ?array
    {
        return MenuPermission::forUserCode($usrcde, 'view_expenses.php');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Expense>
     */
    private function filteredQuery(string $search, string $sort = '', string $dir = 'asc')
    {
        [$column, $direction] = CrudList::order(
            $sort,
            $dir,
            ['docnum', 'trndte', 'itmdsc', 'untprc'],
            'docnum',
            'desc',
        );
        $query = Expense::query()->api()->orderBy($column, $direction);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('docnum', 'like', $like)
                    ->orWhere('itmdsc', 'like', $like);
            });
        }

        return $query;
    }

    private function fallbackDocnum(): string
    {
        $last = Expense::query()->where('docnum', 'like', 'EXP-%')->orderByDesc('docnum')->value('docnum');
        $current = trim((string) ($last ?? ''));
        if ($current === '') {
            return 'EXP-00000001';
        }

        return $this->nextSeries($current);
    }

    /**
     * webmoreta stdfunc01.php LNexts: increment from the right.
     */
    private function nextSeries(string $value): string
    {
        $carry = true;
        $result = '';
        for ($index = strlen($value) - 1; $index >= 0; $index--) {
            $char = $value[$index];
            if (! $carry) {
                return substr($value, 0, $index + 1).$result;
            }

            $code = ord($char);
            if ($code >= 48 && $code <= 57) {
                $carry = $char === '9';
                $char = substr((string) (((int) $char) + 1), -1);
            } elseif ($code >= 65 && $code <= 90) {
                $char = $code === 90 ? 'a' : chr($code + 1);
                $carry = false;
            } elseif ($code >= 97 && $code <= 122) {
                if ($code === 122) {
                    $char = 'A';
                    $carry = true;
                } else {
                    $char = chr($code + 1);
                    $carry = false;
                }
            }

            $result = $char.$result;
        }

        return $result;
    }
}
