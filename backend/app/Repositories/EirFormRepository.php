<?php

namespace App\Repositories;

use App\Models\EirForm;
use App\Models\EirFormSketch;
use App\Support\CrudList;
use App\Support\LegacySchema;
use App\Support\MenuPermission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EirFormRepository
{
    /**
     * @return LengthAwarePaginator<int, EirForm>
     */
    public function paginateForList(string $search, int $perPage, string $sort = '', string $dir = 'asc'): LengthAwarePaginator
    {
        return $this->filteredQuery($search, $sort, $dir)->paginate($perPage);
    }

    /**
     * @return Collection<int, EirForm>
     */
    public function getForList(string $search, string $sort = '', string $dir = 'asc'): Collection
    {
        return $this->filteredQuery($search, $sort, $dir)->get();
    }

    public function findByRecid(int $recid): ?EirForm
    {
        return EirForm::query()->api()->where('recid', $recid)->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): EirForm
    {
        $row = EirForm::query()->create($attributes);

        return EirForm::query()->api()->where('recid', $row->recid)->firstOrFail();
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

    public function deleteByDocnum(string $docnum): void
    {
        EirForm::query()->where('docnum', $docnum)->delete();
        EirFormSketch::query()->where('docnum', $docnum)->delete();
    }

    /**
     * @return Collection<int, EirFormSketch>
     */
    public function sketch(string $docnum): Collection
    {
        return EirFormSketch::query()->where('docnum', $docnum)->orderBy('recid')->get();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function replaceSketch(string $docnum, array $rows): void
    {
        EirFormSketch::query()->where('docnum', $docnum)->delete();
        foreach ($rows as $row) {
            EirFormSketch::query()->create($row);
        }
    }

    public function duplicateMoveDocnum(string $vannum, string $moveField, string $excludeDocnum): ?string
    {
        if (! in_array($moveField, ['pickup', 'return'], true)) {
            return null;
        }

        return EirForm::query()
            ->where('vannum', $vannum)
            ->where('docnum', '!=', $excludeDocnum)
            ->whereRaw("`{$moveField}` IS NOT NULL AND `{$moveField}` != ''")
            ->value('docnum');
    }

    public function nextDocnum(): string
    {
        return (string) DB::transaction(function () {
            $fromTable = $this->latestExistingDocnum();
            $fromSyspar = '';
            $syspar = null;
            $hasSyspar = LegacySchema::hasTable('syspar') && LegacySchema::hasColumn('syspar', 'eir_docnum');
            if ($hasSyspar) {
                $syspar = DB::table('syspar')->where('recid', 1)->lockForUpdate()->first();
                if ($syspar !== null) {
                    $fromSyspar = trim((string) ($syspar->eir_docnum ?? ''));
                }
            }

            $current = $this->higherSeries($fromSyspar, $fromTable);
            if ($current === '') {
                $current = '00000000';
            }

            $next = $this->nextUnusedSeries($current);
            if ($syspar !== null) {
                DB::table('syspar')->where('recid', 1)->update(['eir_docnum' => $next]);
            }

            return $next;
        });
    }

    /**
     * @return list<array{dstcde: string, dstdsc: string}>
     */
    public function destinations(): array
    {
        if (! LegacySchema::hasTable('destinationfile')) {
            return [];
        }

        return DB::table('destinationfile')
            ->orderBy('dstcde')
            ->get(['dstcde', 'dstdsc'])
            ->map(fn ($row) => [
                'dstcde' => trim((string) ($row->dstcde ?? '')),
                'dstdsc' => trim((string) ($row->dstdsc ?? '')),
            ])
            ->filter(fn ($row) => $row['dstdsc'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function containers(): array
    {
        if (! LegacySchema::hasTable('vanfile')) {
            return [];
        }

        return DB::table('vanfile')
            ->orderBy('vannum')
            ->pluck('prevannum')
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array{cuscde: string, cusdsc: string}>
     */
    public function shippers(): array
    {
        if (! LegacySchema::hasTable('customerfile')) {
            return [];
        }

        return DB::table('customerfile')
            ->orderBy('cusdsc')
            ->get(['cuscde', 'cusdsc'])
            ->map(fn ($row) => [
                'cuscde' => trim((string) ($row->cuscde ?? '')),
                'cusdsc' => trim((string) ($row->cusdsc ?? '')),
            ])
            ->filter(fn ($row) => $row['cuscde'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array{concde: string, condsc: string}>
     */
    public function consignees(): array
    {
        if (! LegacySchema::hasTable('consigneefile')) {
            return [];
        }

        return DB::table('consigneefile')
            ->orderBy('concde')
            ->get(['concde', 'condsc'])
            ->map(fn ($row) => [
                'concde' => trim((string) ($row->concde ?? '')),
                'condsc' => trim((string) ($row->condsc ?? '')),
            ])
            ->filter(fn ($row) => $row['concde'] !== '')
            ->values()
            ->all();
    }

    public function containerSize(string $containerNo): ?string
    {
        if (! LegacySchema::hasTable('vanfile') || $containerNo === '') {
            return null;
        }

        $vandsc = trim((string) (DB::table('vanfile')->where('prevannum', $containerNo)->value('vandsc') ?? ''));
        if ($vandsc === '' || ! LegacySchema::hasTable('categoryfile')) {
            return $vandsc !== '' ? $vandsc : null;
        }

        $tag = trim((string) (DB::table('categoryfile')->where('catcde', $vandsc)->value('tag') ?? ''));

        return $tag !== '' ? $tag : null;
    }

    /**
     * @return array{name: string, tin: string, telno: string, faxnum: string, email: string}
     */
    public function company(): array
    {
        $empty = ['name' => '', 'tin' => '', 'telno' => '', 'faxnum' => '', 'email' => ''];
        if (! LegacySchema::hasTable('companyfile')) {
            return $empty;
        }

        $row = DB::table('companyfile')->first();
        if ($row === null) {
            return $empty;
        }

        $name = trim((string) ($row->comdsc ?? ''));
        if ($name === '') {
            $name = trim((string) ($row->companydescription ?? ''));
        }

        return [
            'name' => $name,
            'tin' => trim((string) ($row->companytin ?? '')),
            'telno' => trim((string) ($row->telno ?? '')),
            'faxnum' => trim((string) ($row->faxnum ?? '')),
            'email' => trim((string) ($row->email ?? '')),
        ];
    }

    /**
     * @return array{cusdsc: string, condsc: string, dstdsc: string}
     */
    public function printNames(EirForm $eir): array
    {
        $cusdsc = trim((string) ($eir->cuscde ?? ''));
        $condsc = trim((string) ($eir->concde ?? ''));
        $dstdsc = trim((string) ($eir->dstcde ?? ''));

        if (LegacySchema::hasTable('customerfile') && $cusdsc !== '') {
            $name = trim((string) (DB::table('customerfile')->where('cuscde', $cusdsc)->value('cusdsc') ?? ''));
            if ($name !== '') {
                $cusdsc = $name;
            }
        }
        if (LegacySchema::hasTable('consigneefile') && $condsc !== '') {
            $name = trim((string) (DB::table('consigneefile')->where('concde', $condsc)->value('condsc') ?? ''));
            if ($name !== '') {
                $condsc = $name;
            }
        }
        if (LegacySchema::hasTable('destinationfile') && $dstdsc !== '') {
            $name = trim((string) (DB::table('destinationfile')->where('dstcde', $dstdsc)->value('dstdsc') ?? ''));
            if ($name !== '') {
                $dstdsc = $name;
            }
        }

        return compact('cusdsc', 'condsc', 'dstdsc');
    }

    /**
     * @return array{allow_add: bool, allow_edit: bool, allow_delete: bool, allow_view: bool, allow_print: bool}|null
     */
    public function menuPermissions(string $usrcde): ?array
    {
        return MenuPermission::forUserCode($usrcde, 'view_equip_interchange_receipt.php');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<EirForm>
     */
    private function filteredQuery(string $search, string $sort = '', string $dir = 'asc')
    {
        [$column, $direction] = CrudList::order(
            $sort,
            $dir,
            ['docnum', 'issue_dte', 'origin', 'vannum'],
            'docnum',
            'desc',
        );

        $query = EirForm::query()->api()->orderBy($column, $direction);
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('docnum', 'like', $like)
                    ->orWhere('origin', 'like', $like)
                    ->orWhere('vannum', 'like', $like)
                    ->orWhere('cuscde', 'like', $like)
                    ->orWhere('concde', 'like', $like);
            });
        }

        return $query;
    }

    private function latestExistingDocnum(): string
    {
        $best = '';
        $bestRank = -1;
        foreach (DB::table('eirtranfile1')->pluck('docnum') as $docnum) {
            $value = trim((string) $docnum);
            if ($value === '') {
                continue;
            }
            $rank = $this->seriesRank($value);
            if ($rank > $bestRank) {
                $bestRank = $rank;
                $best = $value;
            }
        }

        return $best;
    }

    private function higherSeries(string $left, string $right): string
    {
        if ($left === '') {
            return $right;
        }
        if ($right === '') {
            return $left;
        }

        return $this->seriesRank($left) >= $this->seriesRank($right) ? $left : $right;
    }

    private function seriesRank(string $value): int
    {
        if (preg_match('/(\d+)$/', $value, $match) !== 1) {
            return 0;
        }

        return (int) $match[1];
    }

    private function nextUnusedSeries(string $current): string
    {
        $next = $this->nextSeries($current);
        $guard = 0;
        while ($next !== '' && $this->docnumExists($next) && $guard < 10000) {
            $next = $this->nextSeries($next);
            $guard++;
        }

        return $next;
    }

    private function docnumExists(string $docnum): bool
    {
        return DB::table('eirtranfile1')->where('docnum', $docnum)->exists();
    }

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
