<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LoadListRepository
{
    public function companyName(): string
    {
        $row = DB::table('companyfile')->first();
        if (! $row) {
            return '';
        }

        if (isset($row->comdsc) && trim((string) $row->comdsc) !== '') {
            return (string) $row->comdsc;
        }

        return (string) ($row->companydescription ?? '');
    }

    /**
     * @return list<array{catcde: string}>
     */
    public function categoriesInBl(): array
    {
        return DB::table('categoryfile')
            ->where('inBL', 'Y')
            ->orderBy('catcde')
            ->pluck('catcde')
            ->map(fn ($value) => ['catcde' => trim((string) $value)])
            ->filter(fn ($row) => $row['catcde'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{catcde: string, iscontainer: string}|null
     */
    public function category(string $catcde): ?array
    {
        $row = DB::table('categoryfile')->where('catcde', $catcde)->first(['catcde', 'iscontainer']);
        if (! $row) {
            return null;
        }

        return [
            'catcde' => trim((string) ($row->catcde ?? '')),
            'iscontainer' => trim((string) ($row->iscontainer ?? '')),
        ];
    }

    public function voyageExists(string $voynum): bool
    {
        return DB::table('voyagefile')->where('voynum', $voynum)->exists();
    }

    /**
     * @return array{date: string, vessel: string}
     */
    public function sailing(string $voynum): array
    {
        $row = DB::table('voyagefile')->where('voynum', $voynum)->first(['trndte', 'vsslcde']);
        $date = now('Asia/Manila')->format('F j, Y');
        $vessel = '';
        if ($row) {
            $stamp = strtotime((string) ($row->trndte ?? ''));
            if ($stamp !== false) {
                $date = date('F j, Y', $stamp);
            }
            $code = trim((string) ($row->vsslcde ?? ''));
            $vessel = $code;
            if ($code !== '') {
                $vesselRow = DB::table('vesselfile')->where('vsslcde', $code)->first(['vssldsc']);
                $name = trim((string) ($vesselRow->vssldsc ?? ''));
                if ($name !== '') {
                    $vessel = $name;
                }
            }
        }

        return ['date' => $date, 'vessel' => $vessel];
    }

    /**
     * @return Collection<int, object>
     */
    public function lines(string $voynum, string $catcde): Collection
    {
        return DB::table('billofladingfile2')
            ->where('catcde', $catcde)
            ->where('voynum', $voynum)
            ->orderBy('docnum')
            ->get([
                'docnum',
                'trncde',
                'vannum',
                'socvannum',
                'cuscde',
                'concde',
                'classdsc',
                'weiamt',
                'qty',
                'plate',
            ]);
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    public function shipperNames(array $codes): array
    {
        $codes = array_values(array_unique(array_filter($codes)));
        if ($codes === []) {
            return [];
        }

        $map = [];
        foreach (array_chunk($codes, 500) as $chunk) {
            $rows = DB::table('customerfile')->whereIn('cuscde', $chunk)->get(['cuscde', 'cusdsc']);
            foreach ($rows as $row) {
                $map[trim((string) ($row->cuscde ?? ''))] = trim((string) ($row->cusdsc ?? ''));
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    public function consigneeNames(array $codes): array
    {
        $codes = array_values(array_unique(array_filter($codes)));
        if ($codes === []) {
            return [];
        }

        $map = [];
        foreach (array_chunk($codes, 500) as $chunk) {
            $rows = DB::table('consigneefile')->whereIn('concde', $chunk)->get(['concde', 'condsc']);
            foreach ($rows as $row) {
                $map[trim((string) ($row->concde ?? ''))] = trim((string) ($row->condsc ?? ''));
            }
        }

        return $map;
    }
}
