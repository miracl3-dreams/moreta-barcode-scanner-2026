<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PostLoadingRepository
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
     * @return list<array{catcde: string, catdsc: string}>
     */
    public function categoriesInBl(): array
    {
        return DB::table('categoryfile')
            ->where('inBL', 'Y')
            ->orderBy('catcde')
            ->get(['catcde', 'catdsc'])
            ->map(fn ($row) => [
                'catcde' => trim((string) ($row->catcde ?? '')),
                'catdsc' => trim((string) ($row->catdsc ?? '')),
            ])
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
                $name = trim((string) (DB::table('vesselfile')->where('vsslcde', $code)->value('vssldsc') ?? ''));
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
    public function listLines(string $voynum): Collection
    {
        return DB::table('billofladingfile2')
            ->where('voynum', $voynum)
            ->where('vannum', '!=', '')
            ->where('docnum', '!=', '')
            ->orderBy('voynum')
            ->get([
                'docnum',
                'catcde',
                'vannum',
                'reason_notloaded',
                'loaded_onvoyage',
                'post_loadingstatus',
            ]);
    }

    /**
     * @return Collection<int, object>
     */
    public function printLines(string $voynum, string $catcde): Collection
    {
        return DB::table('billofladingfile2')
            ->where('catcde', $catcde)
            ->where('voynum', $voynum)
            ->orderBy('recid')
            ->orderBy('trndte')
            ->get([
                'docnum',
                'vannum',
                'cuscde',
                'concde',
                'classdsc',
                'plate',
                'weiamt',
                'reason_notloaded',
                'loaded_onvoyage',
            ]);
    }

    /**
     * @param  array{reason_notloaded: string, loaded_onvoyage: string, post_loadingstatus: string}  $values
     */
    public function updateLine(string $voynum, string $docnum, string $vannum, array $values): void
    {
        DB::table('billofladingfile2')
            ->where('docnum', $docnum)
            ->where('voynum', $voynum)
            ->where('vannum', $vannum)
            ->update($values);

        if (Schema::hasTable('billofladingfile1')) {
            DB::table('billofladingfile1')
                ->where('docnum', $docnum)
                ->where('voynum', $voynum)
                ->update($values);
        }
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
        foreach (DB::table('customerfile')->whereIn('cuscde', $codes)->get(['cuscde', 'cusdsc']) as $row) {
            $map[trim((string) ($row->cuscde ?? ''))] = trim((string) ($row->cusdsc ?? ''));
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
        foreach (DB::table('consigneefile')->whereIn('concde', $codes)->get(['concde', 'condsc']) as $row) {
            $map[trim((string) ($row->concde ?? ''))] = trim((string) ($row->condsc ?? ''));
        }

        return $map;
    }
}
