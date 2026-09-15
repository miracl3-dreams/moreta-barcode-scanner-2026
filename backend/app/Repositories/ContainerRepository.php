<?php

namespace App\Repositories;

use App\Models\Container;
use App\Support\CrudList;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContainerRepository
{
    /**
     * @return LengthAwarePaginator<int, Container>
     */
    public function paginateForList(string $search, int $perPage, string $sort = '', string $dir = 'asc'): LengthAwarePaginator
    {
        return $this->filteredQuery($search, $sort, $dir)->paginate($perPage);
    }

    /**
     * @return Collection<int, Container>
     */
    public function getForList(string $search, string $sort = '', string $dir = 'asc'): Collection
    {
        return $this->filteredQuery($search, $sort, $dir)->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Container
    {
        return Container::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(Container $container, array $attributes): Container
    {
        $container->fill($attributes);
        $container->save();

        return $container;
    }

    public function delete(Container $container): void
    {
        $container->delete();
    }

    public function existsByPrevannum(string $key): bool
    {
        return Container::query()->where('prevannum', $key)->exists();
    }

    public function markConvertedByVanNumber(string $vannum): void
    {
        Container::query()->where('vannum', $vannum)->update(['isConverted' => 1]);
    }

    public function vanNumberUsedInBol(string $vannum): bool
    {
        if (trim($vannum) === '') {
            return false;
        }

        return DB::table('billofladingfile2')
            ->where('vannum', $vannum)
            ->exists();
    }

    /**
     * @param  list<string>  $vannums
     * @return list<string>
     */
    public function usedVanNumbers(array $vannums): array
    {
        $vannums = array_values(array_unique(array_filter(array_map(
            fn ($value) => trim((string) $value),
            $vannums
        ))));
        if ($vannums === []) {
            return [];
        }

        return DB::table('billofladingfile2')
            ->whereIn('vannum', $vannums)
            ->distinct()
            ->pluck('vannum')
            ->map(fn ($value) => (string) $value)
            ->all();
    }

    /**
     * @return array{
     *     prefixes: list<string>,
     *     descriptions: list<string>,
     *     types: list<string>,
     *     destinations: list<array{dstcde: string, dstdsc: string}>
     * }
     */
    public function lookups(): array
    {
        $destinations = DB::table('destinationfile')
            ->orderBy('dstdsc')
            ->get(['dstcde', 'dstdsc'])
            ->map(fn ($row) => [
                'dstcde' => (string) ($row->dstcde ?? ''),
                'dstdsc' => (string) ($row->dstdsc ?? ''),
            ])
            ->values()
            ->all();

        return [
            'prefixes' => DB::table('prefixfile')->orderBy('prefix')->pluck('prefix')->filter()->values()->all(),
            'descriptions' => DB::table('categoryfile')
                ->where('iscontainer', 'Y')
                ->orderBy('catcde')
                ->pluck('catcde')
                ->filter()
                ->values()
                ->all(),
            'types' => DB::table('vantypefile')->orderBy('recid')->pluck('vantype')->filter()->values()->all(),
            'destinations' => $destinations,
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Container>
     */
    private function filteredQuery(string $search, string $sort = '', string $dir = 'asc')
    {
        [$column, $direction] = CrudList::order(
            $sort,
            $dir,
            ['prefix', 'vannum', 'vandsc', 'vantype', 'supplier', 'end_lease', 'contractdate', 'lastloc'],
            'prefix',
        );
        $query = Container::query()
            ->api()
            ->where('isConverted', '!=', 1)
            ->orderBy($column, $direction);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('vannum', 'like', $like)
                    ->orWhere('vandsc', 'like', $like)
                    ->orWhere('lastloc', 'like', $like)
                    ->orWhere('supplier', 'like', $like);
            });
        }

        return $query;
    }
}
