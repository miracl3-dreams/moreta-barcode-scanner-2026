<?php

namespace App\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BillingRegisterRepository
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
     * @return array{
     *     docnums: list<string>,
     *     destinations: list<string>,
     *     shippers: list<string>,
     *     consignees: list<string>,
     *     eir_nos: list<string>
     * }
     */
    public function lookups(): array
    {
        if (! Schema::hasTable('billingtranfile1')) {
            return [
                'docnums' => [],
                'destinations' => [],
                'shippers' => [],
                'consignees' => [],
                'eir_nos' => [],
            ];
        }

        return [
            'docnums' => $this->stringColumn(
                DB::table('billingtranfile1')->orderBy('docnum')->distinct()->pluck('docnum'),
            ),
            'destinations' => $this->stringColumn(
                DB::table('billingtranfile1 as f1')
                    ->leftJoin('destinationfile as dest', 'dest.dstcde', '=', 'f1.dstcde')
                    ->whereNotNull('dest.dstdsc')
                    ->where('dest.dstdsc', '!=', '')
                    ->orderBy('dest.dstdsc')
                    ->distinct()
                    ->pluck('dest.dstdsc'),
            ),
            'shippers' => $this->stringColumn(
                DB::table('billingtranfile1 as f1')
                    ->leftJoin('customerfile as cust', 'cust.cuscde', '=', 'f1.cuscde')
                    ->whereNotNull('cust.cusdsc')
                    ->where('cust.cusdsc', '!=', '')
                    ->orderBy('cust.cusdsc')
                    ->distinct()
                    ->pluck('cust.cusdsc'),
            ),
            'consignees' => $this->stringColumn(
                DB::table('billingtranfile1 as f1')
                    ->leftJoin('consigneefile as cons', 'cons.concde', '=', 'f1.concde')
                    ->whereNotNull('cons.condsc')
                    ->where('cons.condsc', '!=', '')
                    ->orderBy('cons.condsc')
                    ->distinct()
                    ->pluck('cons.condsc'),
            ),
            'eir_nos' => $this->stringColumn(
                DB::table('billingtranfile1')
                    ->whereNotNull('eir_no')
                    ->where('eir_no', '!=', '')
                    ->orderBy('eir_no')
                    ->distinct()
                    ->pluck('eir_no'),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function rows(array $filters): Collection
    {
        if (! Schema::hasTable('billingtranfile1')) {
            return collect();
        }

        $query = DB::table('billingtranfile1 as f1')
            ->leftJoin('customerfile as cust', 'cust.cuscde', '=', 'f1.cuscde')
            ->leftJoin('consigneefile as cons', 'cons.concde', '=', 'f1.concde')
            ->leftJoin('destinationfile as dest', 'dest.dstcde', '=', 'f1.dstcde')
            ->leftJoin('billingtranfile2 as f2', 'f2.docnum', '=', 'f1.docnum')
            ->where('f1.recid', '>', 0)
            ->selectRaw('
                f1.docnum,
                MIN(f1.trndte) as trndte,
                MAX(f1.del_dte) as del_dte,
                MAX(dest.dstdsc) as dstdsc,
                MAX(cust.cusdsc) as cusdsc,
                MAX(cons.condsc) as condsc,
                MAX(f2.weight) as weight,
                MAX(f2.measurement) as measurement,
                MAX(f2.qty) as qty,
                MAX(f2.unit) as unit,
                MAX(f2.itmdesc) as itmdesc,
                MAX(f2.value) as value,
                MAX(f1.vannum) as vannum,
                MAX(f1.eir_no) as eir_no
            ')
            ->groupBy('f1.docnum')
            ->orderBy('trndte');

        $this->applyFilters($query, $filters);

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $from = trim((string) ($filters['date_from'] ?? ''));
        $to = trim((string) ($filters['date_to'] ?? ''));
        if ($from !== '') {
            $query->where('f1.del_dte', '>=', $from);
        }
        if ($to !== '') {
            $query->where('f1.del_dte', '<=', $to);
        }

        $docFrom = trim((string) ($filters['docnum_from'] ?? ''));
        $docTo = trim((string) ($filters['docnum_to'] ?? ''));
        if ($docFrom !== '' && $docTo !== '') {
            $query->whereBetween('f1.docnum', [$docFrom, $docTo]);
        } elseif ($docFrom !== '') {
            $query->where('f1.docnum', $docFrom);
        } elseif ($docTo !== '') {
            $query->where('f1.docnum', $docTo);
        }

        $destination = trim((string) ($filters['destination'] ?? ''));
        if ($destination !== '') {
            $query->where('dest.dstdsc', $destination);
        }
        $shipper = trim((string) ($filters['shipper'] ?? ''));
        if ($shipper !== '') {
            $query->where('cust.cusdsc', $shipper);
        }
        $consignee = trim((string) ($filters['consignee'] ?? ''));
        if ($consignee !== '') {
            $query->where('cons.condsc', $consignee);
        }
        $eir = trim((string) ($filters['eir_docnum'] ?? ''));
        if ($eir !== '') {
            $query->where('f1.eir_no', $eir);
        }
    }

    /**
     * @param  Collection<int, mixed>  $values
     * @return list<string>
     */
    private function stringColumn(Collection $values): array
    {
        $out = [];
        foreach ($values as $value) {
            $text = trim((string) $value);
            if ($text !== '') {
                $out[] = $text;
            }
        }

        return array_values(array_unique($out));
    }
}
