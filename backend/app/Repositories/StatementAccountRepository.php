<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StatementAccountRepository
{
    /**
     * @return array{name: string, add1: string, add2: string}
     */
    public function company(): array
    {
        $row = DB::table('companyfile')->first();
        if (! $row) {
            return ['name' => '', 'add1' => '', 'add2' => ''];
        }

        $name = '';
        if (isset($row->comdsc) && trim((string) $row->comdsc) !== '') {
            $name = (string) $row->comdsc;
        } else {
            $name = (string) ($row->companydescription ?? '');
        }

        return [
            'name' => $name,
            'add1' => trim((string) ($row->companyadd1 ?? '')),
            'add2' => trim((string) ($row->companyadd2 ?? '')),
        ];
    }

    /**
     * @return list<array{dstcde: string, dstdsc: string}>
     */
    public function destinations(): array
    {
        return DB::table('destinationfile')
            ->orderBy('dstdsc')
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
     * @param  array{
     *     voynum?: string,
     *     from?: string,
     *     to?: string,
     *     payee?: string,
     *     code?: string,
     *     paid?: bool,
     *     paytyp?: string,
     *     dstcde?: string,
     *     outstanding?: bool,
     *     sort?: string
     * }  $filters
     * @return Collection<int, object>
     */
    public function bills(array $filters): Collection
    {
        $query = DB::table('billofladingfile1')->select([
            'trndte',
            'boldte',
            'voynum',
            'docnum',
            'docapp',
            'trntot',
            'frghtamt',
            'othrchrgamt',
            'docbal',
            'cuscde',
            'concde',
            'cretrmcde',
            'paytyp',
            'dstcde',
        ]);

        $voynum = trim((string) ($filters['voynum'] ?? ''));
        if ($voynum !== '') {
            $query->where('voynum', $voynum);
        }
        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '') {
            $query->where('trndte', '>=', $from);
        }
        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '') {
            $query->where('trndte', '<=', $to);
        }
        $payee = trim((string) ($filters['payee'] ?? ''));
        $code = trim((string) ($filters['code'] ?? ''));
        if ($code !== '') {
            $column = $payee === 'consignee' ? 'concde' : 'cuscde';
            $query->where($column, $code);
        }
        $paytyp = trim((string) ($filters['paytyp'] ?? ''));
        if ($paytyp !== '') {
            $query->where('paytyp', $paytyp);
        }
        $dstcde = trim((string) ($filters['dstcde'] ?? ''));
        if ($dstcde !== '') {
            $query->where('dstcde', $dstcde);
        }
        if (! empty($filters['paid'])) {
            $query->where('docbal', 0);
        }
        if (! empty($filters['outstanding'])) {
            $query->where('docbal', '>', 0);
        }

        $sort = (string) ($filters['sort'] ?? 'trndte,voynum,docnum');
        if ($sort === 'concde') {
            $query->orderBy('concde');
        } elseif ($sort === 'cuscde') {
            $query->orderBy('cuscde');
        } elseif ($sort === 'docnum') {
            $query->orderBy('docnum');
        } elseif ($sort === 'docnum,voynum,trndte') {
            $query->orderBy('docnum')->orderBy('voynum')->orderBy('trndte');
        } else {
            $query->orderBy('trndte')->orderBy('voynum')->orderBy('docnum');
        }

        return $query->get();
    }

    /**
     * @param  list<string>  $docapps
     * @return array<string, list<array{amtapp: float, docnum: string}>>
     */
    public function applicationsByDocapp(array $docapps): array
    {
        $docapps = array_values(array_unique(array_filter($docapps)));
        if ($docapps === []) {
            return [];
        }

        $rows = DB::table('arpaymentapplication')
            ->whereIn('docapp', $docapps)
            ->get(['docapp', 'amtapp', 'docnum']);

        $map = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row->docapp ?? ''));
            $map[$key][] = [
                'amtapp' => (float) ($row->amtapp ?? 0),
                'docnum' => trim((string) ($row->docnum ?? '')),
            ];
        }

        return $map;
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, int>
     */
    public function termDaysByCode(array $codes): array
    {
        $codes = array_values(array_unique(array_filter($codes)));
        if ($codes === []) {
            return [];
        }

        $rows = DB::table('termfile')->whereIn('trmcde', $codes)->get(['trmcde', 'trmday']);
        $map = [];
        foreach ($rows as $row) {
            $map[trim((string) ($row->trmcde ?? ''))] = (int) ($row->trmday ?? 0);
        }

        return $map;
    }

    public function voyageExists(string $voynum): bool
    {
        if ($voynum === '') {
            return false;
        }

        return DB::table('voyagefile')->where('voynum', $voynum)->exists();
    }

    public function voyageLikeCount(string $prefix): int
    {
        if ($prefix === '') {
            return 0;
        }

        return DB::table('voyagefile')->where('voynum', 'like', $prefix.'%')->count();
    }

    /**
     * @return list<array{usrdte: string, usrtim: string, remarks: string}>
     */
    public function activitiesForDocnum(string $docnum): array
    {
        if ($docnum === '' || ! Schema::hasTable('user_activities')) {
            return [];
        }

        return DB::table('user_activities')
            ->where('remarks', 'like', '%'.$docnum.'%')
            ->get(['usrdte', 'usrtim', 'remarks'])
            ->map(fn ($row) => [
                'usrdte' => trim((string) ($row->usrdte ?? '')),
                'usrtim' => trim((string) ($row->usrtim ?? '')),
                'remarks' => trim((string) ($row->remarks ?? '')),
            ])
            ->all();
    }
}
