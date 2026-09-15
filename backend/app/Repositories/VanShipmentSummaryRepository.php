<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VanShipmentSummaryRepository
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
     * @return list<array{tag: string, catcde: string}>
     */
    public function headerCategories(): array
    {
        return DB::table('categoryfile')
            ->where('iscontainer', 'Y')
            ->where('isHeader', '1')
            ->orderBy('catcde')
            ->get(['tag', 'catcde'])
            ->map(fn ($row) => [
                'tag' => trim((string) ($row->tag ?? '')),
                'catcde' => trim((string) ($row->catcde ?? '')),
            ])
            ->filter(fn ($row) => $row['catcde'] !== '')
            ->values()
            ->all();
    }

    public function shipperName(string $cuscde): string
    {
        if ($cuscde === '') {
            return '';
        }

        $row = DB::table('customerfile')->where('cuscde', $cuscde)->first(['cusdsc']);

        return trim((string) ($row->cusdsc ?? ''));
    }

    public function consigneeName(string $concde): string
    {
        if ($concde === '') {
            return '';
        }

        $row = DB::table('consigneefile')->where('concde', $concde)->first(['condsc']);

        return trim((string) ($row->condsc ?? ''));
    }

    /**
     * @return list<string>
     */
    public function partyCodes(
        string $payee,
        string $code,
        string $origin,
        string $dstcde,
        string $from,
        string $to,
    ): array {
        $column = $payee === 'consignee' ? 'concde' : 'cuscde';
        $name = $payee === 'consignee' ? 'condsc' : 'cusdsc';

        $query = $this->filteredBills($origin, $dstcde, $from, $to)
            ->select($column)
            ->where('payee', $payee)
            ->where($column, '<>', '')
            ->groupBy($column)
            ->orderByRaw('MIN('.$name.')');

        if ($code !== '') {
            $query->where($column, $code);
        }

        return $query->pluck($column)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, object>
     */
    public function voyages(
        string $payee,
        string $code,
        string $origin,
        string $dstcde,
        string $from,
        string $to,
    ): Collection {
        $column = $payee === 'consignee' ? 'concde' : 'cuscde';

        return $this->filteredBills($origin, $dstcde, $from, $to)
            ->select(['voynum', DB::raw('MIN(docnum) as docnum')])
            ->where('payee', $payee)
            ->where($column, $code)
            ->groupBy('voynum')
            ->orderBy('voynum')
            ->get();
    }

    /**
     * @param  list<string>  $codes
     * @param  list<string>  $voynums
     * @param  list<string>  $categories
     * @return array<string, int>
     */
    public function categoryCounts(
        string $payee,
        array $codes,
        array $voynums,
        array $categories,
    ): array {
        $column = $payee === 'consignee' ? 'concde' : 'cuscde';
        $map = [];
        $codes = array_values(array_unique(array_filter($codes)));
        $voynums = array_values(array_unique(array_filter($voynums)));
        $categories = array_values(array_unique(array_filter($categories)));
        if ($codes === [] || $voynums === [] || $categories === []) {
            return $map;
        }

        foreach (array_chunk($voynums, 500) as $voyageChunk) {
            foreach (array_chunk($codes, 500) as $codeChunk) {
                $rows = DB::table('billofladingfile2')
                    ->select(['voynum', $column, 'catcde', DB::raw('COUNT(*) as cnt')])
                    ->where('trncde', 'BL')
                    ->whereIn('catcde', $categories)
                    ->whereIn('voynum', $voyageChunk)
                    ->whereIn($column, $codeChunk)
                    ->groupBy('voynum', $column, 'catcde')
                    ->get();

                foreach ($rows as $row) {
                    $key = trim((string) $row->voynum).'|'.trim((string) $row->{$column}).'|'.trim((string) $row->catcde);
                    $map[$key] = (int) $row->cnt;
                }
            }
        }

        return $map;
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function filteredBills(string $origin, string $dstcde, string $from, string $to)
    {
        $query = DB::table('billofladingfile1');
        if ($from !== '') {
            $query->where('trndte', '>=', $from);
        }
        if ($to !== '') {
            $query->where('trndte', '<=', $to);
        }
        $query->where('origin', $origin)->where('dstcde', $dstcde);

        return $query;
    }
}
