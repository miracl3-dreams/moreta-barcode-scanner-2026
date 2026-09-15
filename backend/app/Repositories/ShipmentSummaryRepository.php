<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShipmentSummaryRepository
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
     * @return list<array{code: string, label: string}>
     */
    public function searchShippers(string $term): array
    {
        $query = DB::table('customerfile')->orderBy('cusdsc')->limit(5);
        if (trim($term) !== '') {
            $query->where('cusdsc', 'like', '%'.trim($term).'%');
        }

        return $query->get(['cuscde', 'cusdsc'])
            ->map(fn ($row) => [
                'code' => trim((string) ($row->cuscde ?? '')),
                'label' => trim((string) ($row->cusdsc ?? '')),
            ])
            ->filter(fn ($row) => $row['code'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public function searchConsignees(string $term): array
    {
        $query = DB::table('consigneefile')->orderBy('condsc')->limit(10);
        if (trim($term) !== '') {
            $query->where('condsc', 'like', '%'.trim($term).'%');
        }

        return $query->get(['concde', 'condsc'])
            ->map(fn ($row) => [
                'code' => trim((string) ($row->concde ?? '')),
                'label' => trim((string) ($row->condsc ?? '')),
            ])
            ->filter(fn ($row) => $row['code'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public function searchVoyages(string $term): array
    {
        $query = DB::table('voyagefile')->orderBy('voynum')->limit(5);
        if (trim($term) !== '') {
            $query->where('voynum', 'like', trim($term).'%');
        }

        return $query->get(['voynum'])
            ->map(fn ($row) => [
                'code' => trim((string) ($row->voynum ?? '')),
                'label' => trim((string) ($row->voynum ?? '')),
            ])
            ->filter(fn ($row) => $row['code'] !== '')
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
    public function payerCodes(string $payee, string $code): array
    {
        $column = $payee === 'consignee' ? 'concde' : 'cuscde';
        $query = DB::table('billofladingfile1')
            ->where('payee', $payee)
            ->where($column, '<>', '')
            ->groupBy($column)
            ->orderBy($column);

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
    public function bills(
        string $payee,
        string $code,
        string $filter,
        string $dstcde,
        string $voynum,
        string $from,
        string $to,
    ): Collection {
        $column = $payee === 'consignee' ? 'concde' : 'cuscde';
        $query = DB::table('billofladingfile1')
            ->select(['docnum', 'voynum', 'trndte', 'dstcde', 'cuscde', 'concde'])
            ->where('payee', $payee)
            ->where($column, $code);

        if ($filter === 'bydst' && $dstcde !== '') {
            $query->where('dstcde', $dstcde);
        }
        if ($filter === 'byvoy' && $voynum !== '') {
            $query->where('voynum', $voynum);
        }
        if ($filter === 'bydte') {
            if ($from !== '') {
                $query->where('trndte', '>=', $from);
            }
            if ($to !== '') {
                $query->where('trndte', '<=', $to);
            }
        }

        return $query->orderBy('trndte')->orderBy('voynum')->orderBy('docnum')->get();
    }

    /**
     * @param  list<string>  $docnums
     * @return Collection<int, object>
     */
    public function cargoLines(array $docnums): Collection
    {
        $rows = collect();
        foreach (array_chunk(array_values(array_unique(array_filter($docnums))), 500) as $chunk) {
            $rows = $rows->concat(
                DB::table('billofladingfile2')
                    ->select(['docnum', 'soc', 'socvannum', 'vannum', 'itmqty', 'classdsc', 'catcde', 'linenum', 'recid'])
                    ->where('trncde', 'BL')
                    ->whereIn('docnum', $chunk)
                    ->orderBy('linenum')
                    ->orderBy('recid')
                    ->get(),
            );
        }

        return $rows;
    }
}
