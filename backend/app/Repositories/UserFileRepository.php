<?php

namespace App\Repositories;

use App\Models\UserFile;
use App\Support\CrudList;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserFileRepository
{
    /**
     * @return LengthAwarePaginator<int, UserFile>
     */
    public function paginateForList(string $search, int $perPage, string $sort = '', string $dir = 'asc'): LengthAwarePaginator
    {
        return $this->filteredQuery($search, $sort, $dir)->paginate($perPage);
    }

    /**
     * @return Collection<int, UserFile>
     */
    public function getForList(string $search, string $sort = '', string $dir = 'asc'): Collection
    {
        return $this->filteredQuery($search, $sort, $dir)->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): UserFile
    {
        return UserFile::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(UserFile $user, array $attributes): UserFile
    {
        $user->fill($attributes);
        $user->save();

        return $user;
    }

    public function delete(UserFile $user): void
    {
        $code = trim((string) ($user->usrcde ?? ''));
        if ($code !== '' && Schema::hasTable('user_menus')) {
            DB::table('user_menus')->where('usrcde', $code)->delete();
        }
        $user->delete();
    }

    public function resetLogin(UserFile $user): void
    {
        $user->status = '0';
        $user->save();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function levels(): array
    {
        if (! Schema::hasTable('userlevelfile')) {
            return [];
        }

        $out = [];
        foreach (DB::table('userlevelfile')->orderBy('usrlvl')->get(['usrlvl']) as $row) {
            $value = trim((string) ($row->usrlvl ?? ''));
            if ($value !== '') {
                $out[] = ['value' => $value, 'label' => $value];
            }
        }

        return $out;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function branches(): array
    {
        if (! Schema::hasTable('destinationfile')) {
            return [];
        }

        $out = [];
        foreach (DB::table('destinationfile')->orderBy('dstdsc')->get(['dstcde', 'dstdsc']) as $row) {
            $value = trim((string) ($row->dstcde ?? ''));
            if ($value !== '') {
                $out[] = [
                    'value' => $value,
                    'label' => trim((string) ($row->dstdsc ?? $value)),
                ];
            }
        }

        return $out;
    }

    /**
     * @return Collection<int, object>
     */
    public function menus(): Collection
    {
        return DB::table('menus')
            ->whereNotIn('menidx', ['x', 'X'])
            ->orderBy('menidx')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function userMenus(string $usrcde): Collection
    {
        if (! Schema::hasTable('user_menus') || $usrcde === '') {
            return collect();
        }

        return DB::table('user_menus')->where('usrcde', $usrcde)->get();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function replaceUserMenus(string $usrcde, array $rows): void
    {
        if (! Schema::hasTable('user_menus')) {
            return;
        }
        DB::table('user_menus')->where('usrcde', $usrcde)->delete();
        foreach ($rows as $row) {
            DB::table('user_menus')->insert($row);
        }
    }

    /**
     * @return Collection<int, object>
     */
    public function charges(string $userRecid): Collection
    {
        if (! Schema::hasTable('chargesfile2')) {
            return collect();
        }

        return DB::table('chargesfile2')
            ->where('usercde', $userRecid)
            ->orderBy('sortorder')
            ->get();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function replaceCharges(string $userRecid, array $rows): void
    {
        if (! Schema::hasTable('chargesfile2')) {
            return;
        }
        DB::table('chargesfile2')->where('usercde', $userRecid)->delete();
        foreach ($rows as $row) {
            DB::table('chargesfile2')->insert($row);
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<UserFile>
     */
    private function filteredQuery(string $search, string $sort = '', string $dir = 'asc')
    {
        [$column, $direction] = CrudList::order($sort, $dir, ['usrcde', 'usrname', 'usrlvl'], 'usrcde');
        $query = UserFile::query()->api()->orderBy($column, $direction);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('usrcde', 'like', $like)
                    ->orWhere('usrname', 'like', $like)
                    ->orWhere('usrlvl', 'like', $like);
            });
        }

        return $query;
    }
}
