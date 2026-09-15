<?php

namespace App\Repositories;

use App\Models\VanTransportation;
use App\Support\CrudList;
use App\Support\MenuPermission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VanTransportationRepository
{
    /**
     * @return LengthAwarePaginator<int, VanTransportation>
     */
    public function paginateForList(string $search, int $perPage, string $sort = '', string $dir = 'asc'): LengthAwarePaginator
    {
        return $this->filteredQuery($search, $sort, $dir)->paginate($perPage);
    }

    /**
     * @return Collection<int, VanTransportation>
     */
    public function getForList(string $search, string $sort = '', string $dir = 'asc'): Collection
    {
        return $this->filteredQuery($search, $sort, $dir)->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): VanTransportation
    {
        $row = VanTransportation::query()->create($attributes);

        return VanTransportation::query()->api()->where('recid', $row->recid)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(VanTransportation $row, array $attributes): VanTransportation
    {
        $row->fill($attributes);
        $row->save();

        return VanTransportation::query()->api()->where('recid', $row->recid)->firstOrFail();
    }

    public function delete(VanTransportation $row): void
    {
        $row->delete();
    }

    /**
     * Next van transport number from syspar.aprdocnum (ajax_getdocnum.php xaction=vantranspo).
     * Assigned value is the current series; the incremented value is stored.
     */
    public function nextDocnum(): string
    {
        return (string) DB::transaction(function () {
            if (! Schema::hasTable('syspar') || ! Schema::hasColumn('syspar', 'aprdocnum')) {
                return $this->fallbackDocnum();
            }

            $row = DB::table('syspar')->lockForUpdate()->first();
            if ($row === null) {
                return $this->fallbackDocnum();
            }

            $current = trim((string) ($row->aprdocnum ?? ''));
            if ($current === '') {
                $current = 'VT-00000001';
            }

            $next = $this->nextSeries($current);
            $update = ['aprdocnum' => $next];
            if (Schema::hasColumn('syspar', 'is_arrlock')) {
                $update['is_arrlock'] = 0;
            }
            DB::table('syspar')->update($update);

            return $current;
        });
    }

    /**
     * @return list<array{dstcde: string, dstdsc: string}>
     */
    public function destinations(): array
    {
        if (! Schema::hasTable('destinationfile')) {
            return [];
        }

        return DB::table('destinationfile')
            ->orderBy('dstcde')
            ->get(['dstcde', 'dstdsc'])
            ->map(fn ($row) => [
                'dstcde' => trim((string) ($row->dstcde ?? '')),
                'dstdsc' => trim((string) ($row->dstdsc ?? '')),
            ])
            ->filter(fn ($row) => $row['dstcde'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public function searchPayers(string $type, string $term): array
    {
        $like = '%'.$term.'%';
        if ($type === 'consignee') {
            if (! Schema::hasTable('consigneefile')) {
                return [];
            }

            return DB::table('consigneefile')
                ->where('condsc', 'like', $like)
                ->orderBy('condsc')
                ->limit(8)
                ->get(['concde', 'condsc'])
                ->map(fn ($row) => [
                    'code' => trim((string) ($row->concde ?? '')),
                    'label' => trim((string) ($row->condsc ?? '')),
                ])
                ->values()
                ->all();
        }

        if (! Schema::hasTable('customerfile')) {
            return [];
        }

        return DB::table('customerfile')
            ->where('cusdsc', 'like', $like)
            ->orderBy('cusdsc')
            ->limit(8)
            ->get(['cuscde', 'cusdsc'])
            ->map(fn ($row) => [
                'code' => trim((string) ($row->cuscde ?? '')),
                'label' => trim((string) ($row->cusdsc ?? '')),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public function searchVans(string $term): array
    {
        if (! Schema::hasTable('vanfile')) {
            return [];
        }

        $query = DB::table('vanfile')->orderBy('vannum')->limit(20);
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('prevannum', 'like', $like)
                    ->orWhere('vannum', 'like', $like);
            });
        }

        return $query
            ->get(['prevannum'])
            ->map(fn ($row) => [
                'code' => trim((string) ($row->prevannum ?? '')),
                'label' => trim((string) ($row->prevannum ?? '')),
            ])
            ->filter(fn ($row) => $row['code'] !== '')
            ->unique('code')
            ->values()
            ->all();
    }

    /**
     * webmoreta func_moreta.php GetVanLastLocation.
     * Returns null when the latest cargo/VT date is after $asOf (do not move lastloc).
     */
    public function vanLastLocation(string $vannum, string $asOf): ?string
    {
        if ($vannum === '' || $asOf === '') {
            return null;
        }

        $latest = DB::table('billofladingfile2')
            ->where('vannum', $vannum)
            ->orderByDesc('trndte')
            ->orderByDesc('recid')
            ->first();

        if ($latest === null) {
            return '';
        }

        $latestDate = $this->dateTimeString($latest->trndte ?? null);
        $asOfDate = $this->dateTimeString($asOf);
        if ($latestDate !== '' && $asOfDate !== '' && $latestDate > $asOfDate) {
            return null;
        }

        $trncde = strtoupper(trim((string) ($latest->trncde ?? '')));
        if ($trncde === 'BL' || $trncde === 'EV') {
            $voynum = trim((string) ($latest->voynum ?? ''));
            if ($voynum === '' || ! Schema::hasTable('voyagefile')) {
                return '';
            }

            $voyage = DB::table('voyagefile')->where('voynum', $voynum)->first(['dstcde']);

            return trim((string) ($voyage->dstcde ?? ''));
        }

        if ($trncde === 'VT') {
            if (strtoupper(trim((string) ($latest->vt_status ?? ''))) === 'BORROWED') {
                return trim((string) ($latest->origin ?? ''));
            }

            return trim((string) ($latest->dstcde ?? ''));
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateVanLocation(string $vannum, array $attributes): void
    {
        if ($vannum === '' || $attributes === [] || ! Schema::hasTable('vanfile')) {
            return;
        }

        DB::table('vanfile')
            ->where(function ($query) use ($vannum) {
                $query->where('prevannum', $vannum)->orWhere('vannum', $vannum);
            })
            ->update($attributes);
    }

    public function vanLocationColumns(): array
    {
        return [
            'remarksloc' => Schema::hasTable('vanfile') && Schema::hasColumn('vanfile', 'remarksloc'),
            'trncde' => Schema::hasTable('vanfile') && Schema::hasColumn('vanfile', 'trncde'),
        ];
    }

    /**
     * @return array{allow_add: bool, allow_edit: bool, allow_delete: bool, allow_view: bool}|null
     */
    public function menuPermissions(string $usrcde): ?array
    {
        return MenuPermission::forUserCode($usrcde, 'view_van_transportation.php');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<VanTransportation>
     */
    private function filteredQuery(string $search, string $sort = '', string $dir = 'asc')
    {
        [$column, $direction] = CrudList::order(
            $sort,
            $dir,
            ['docnum', 'cusdsc', 'condsc', 'vannum', 'origin', 'dteout', 'dstcde', 'dteret'],
            'docnum',
            'desc',
        );
        $query = VanTransportation::query()->api()->orderBy($column, $direction);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('docnum', 'like', $like)
                    ->orWhere('cusdsc', 'like', $like)
                    ->orWhere('condsc', 'like', $like)
                    ->orWhere('vannum', 'like', $like)
                    ->orWhere('origin', 'like', $like)
                    ->orWhere('dstcde', 'like', $like);
            });
        }

        return $query;
    }

    private function fallbackDocnum(): string
    {
        $last = VanTransportation::query()->where('docnum', 'like', 'VT-%')->orderByDesc('docnum')->value('docnum');
        $current = trim((string) ($last ?? ''));
        if ($current === '') {
            return 'VT-00000001';
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

    private function dateTimeString(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return '';
        }

        $stamp = substr($text, 0, 19);
        if (strlen($stamp) === 10) {
            return $stamp.' 00:00:00';
        }

        return $stamp;
    }
}
