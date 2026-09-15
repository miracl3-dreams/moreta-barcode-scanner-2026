<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ManifestCargoRepository
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
     * @return array{voynum: string, vsslcde: string, trndte: string, etadte: string, origin: string, dstcde: string}|null
     */
    public function voyage(string $voynum): ?array
    {
        $row = DB::table('voyagefile')->where('voynum', $voynum)->first([
            'voynum',
            'vsslcde',
            'trndte',
            'etadte',
            'origin',
            'dstcde',
        ]);
        if (! $row) {
            return null;
        }

        return [
            'voynum' => trim((string) ($row->voynum ?? '')),
            'vsslcde' => trim((string) ($row->vsslcde ?? '')),
            'trndte' => trim((string) ($row->trndte ?? '')),
            'etadte' => trim((string) ($row->etadte ?? '')),
            'origin' => trim((string) ($row->origin ?? '')),
            'dstcde' => trim((string) ($row->dstcde ?? '')),
        ];
    }

    /**
     * @return list<string>
     */
    public function chargeGroups(): array
    {
        $rows = DB::table('chargesfile')
            ->where('is_show', 1)
            ->where('chargefld', '<>', 'vatamt')
            ->orderBy('sortorder')
            ->get(['grpcde', 'chargefld']);

        $groups = [];
        foreach ($rows as $row) {
            $group = trim((string) ($row->grpcde ?? ''));
            if ($group === '' || in_array($group, $groups, true)) {
                continue;
            }
            $groups[] = $group;
        }

        return $groups;
    }

    /**
     * @param  list<string>  $groups
     * @return array<string, list<string>>
     */
    public function chargeFieldsByGroup(array $groups): array
    {
        $map = [];
        foreach ($groups as $group) {
            $map[$group] = [];
        }
        if ($groups === []) {
            return $map;
        }

        $rows = DB::table('chargesfile')->whereIn('grpcde', $groups)->get(['grpcde', 'chargefld']);
        foreach ($rows as $row) {
            $group = trim((string) ($row->grpcde ?? ''));
            $field = trim((string) ($row->chargefld ?? ''));
            if ($group === '' || $field === '' || ! isset($map[$group])) {
                continue;
            }
            if (! in_array($field, $map[$group], true)) {
                $map[$group][] = $field;
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $extraFields
     * @return Collection<int, object>
     */
    public function bills(string $voynum, array $extraFields, bool $excludeCancelled): Collection
    {
        $columns = ['recid', 'docnum', 'cuscde', 'concde', 'trntot', 'shipmode', 'docapp'];
        foreach ($extraFields as $field) {
            if (! in_array($field, $columns, true) && Schema::hasColumn('billofladingfile1', $field)) {
                $columns[] = $field;
            }
        }

        $query = DB::table('billofladingfile1')->where('voynum', $voynum);
        if ($excludeCancelled) {
            $query->where('cuscde', '!=', 'CANCELLED');
        }

        return $query->orderBy('docnum')->get($columns);
    }

    /**
     * @return Collection<int, object>
     */
    public function cargoLines(string $docnum, string $orderBy = 'vannum'): Collection
    {
        $orderBy = in_array($orderBy, ['vannum', 'linenum'], true) ? $orderBy : 'vannum';

        return DB::table('billofladingfile2')
            ->where('docnum', $docnum)
            ->where('trncde', 'BL')
            ->where('catcde', '<>', '')
            ->whereNotNull('catcde')
            ->orderBy($orderBy)
            ->get([
                'itmcde',
                'class',
                'qty',
                'catcde',
                'soc',
                'socvannum',
                'vannum',
                'sealnum',
                'classdsc',
                'itmqty',
                'weiamt',
                'value',
            ]);
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    public function shipperNames(array $codes): array
    {
        return $this->nameMap('customerfile', 'cuscde', 'cusdsc', $codes);
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    public function consigneeNames(array $codes): array
    {
        return $this->nameMap('consigneefile', 'concde', 'condsc', $codes);
    }

    /**
     * @param  list<string>  $docapps
     * @return array<string, array{ornum: string, ordate: string}>
     */
    public function receiptsByDocapp(array $docapps): array
    {
        $docapps = array_values(array_unique(array_filter($docapps)));
        if ($docapps === []) {
            return [];
        }

        $map = [];
        foreach (array_chunk($docapps, 500) as $chunk) {
            $apps = DB::table('arpaymentapplication')
                ->whereIn('docapp', $chunk)
                ->orderBy('recid')
                ->get(['docapp', 'docnum']);
            $orByApp = [];
            foreach ($apps as $row) {
                $app = trim((string) ($row->docapp ?? ''));
                if ($app === '' || isset($orByApp[$app])) {
                    continue;
                }
                $orByApp[$app] = trim((string) ($row->docnum ?? ''));
            }
            $ornums = array_values(array_filter($orByApp));
            $dates = [];
            if ($ornums !== []) {
                foreach (DB::table('arpaymentsfile1')->whereIn('docnum', $ornums)->get(['docnum', 'trndte']) as $or) {
                    $dates[trim((string) ($or->docnum ?? ''))] = trim((string) ($or->trndte ?? ''));
                }
            }
            foreach ($orByApp as $app => $ornum) {
                $map[$app] = [
                    'ornum' => $ornum,
                    'ordate' => $dates[$ornum] ?? '',
                ];
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    private function nameMap(string $table, string $codeColumn, string $nameColumn, array $codes): array
    {
        $codes = array_values(array_unique(array_filter($codes)));
        if ($codes === []) {
            return [];
        }

        $map = [];
        foreach (array_chunk($codes, 500) as $chunk) {
            foreach (DB::table($table)->whereIn($codeColumn, $chunk)->get([$codeColumn, $nameColumn]) as $row) {
                $map[trim((string) ($row->{$codeColumn} ?? ''))] = trim((string) ($row->{$nameColumn} ?? ''));
            }
        }

        return $map;
    }
}
