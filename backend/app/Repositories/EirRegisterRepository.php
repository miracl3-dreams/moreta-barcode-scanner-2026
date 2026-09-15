<?php

namespace App\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EirRegisterRepository
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
     *     shippers: list<string>,
     *     consignees: list<string>,
     *     billing_nos: list<string>,
     *     van_statuses: list<string>
     * }
     */
    public function lookups(): array
    {
        if (! Schema::hasTable('eirtranfile1')) {
            return [
                'docnums' => [],
                'shippers' => [],
                'consignees' => [],
                'billing_nos' => [],
                'van_statuses' => [],
            ];
        }

        return [
            'docnums' => $this->stringColumn(
                DB::table('eirtranfile1')->orderBy('docnum')->distinct()->pluck('docnum'),
            ),
            'shippers' => $this->stringColumn(
                DB::table('eirtranfile1 as f1')
                    ->leftJoin('customerfile as cust', 'cust.cuscde', '=', 'f1.cuscde')
                    ->whereNotNull('cust.cusdsc')
                    ->where('cust.cusdsc', '!=', '')
                    ->orderBy('cust.cusdsc')
                    ->distinct()
                    ->pluck('cust.cusdsc'),
            ),
            'consignees' => $this->stringColumn(
                DB::table('eirtranfile1 as f1')
                    ->leftJoin('consigneefile as cons', 'cons.concde', '=', 'f1.concde')
                    ->whereNotNull('cons.condsc')
                    ->where('cons.condsc', '!=', '')
                    ->orderBy('cons.condsc')
                    ->distinct()
                    ->pluck('cons.condsc'),
            ),
            'billing_nos' => $this->stringColumn(
                DB::table('eirtranfile1')
                    ->whereNotNull('billing_no')
                    ->where('billing_no', '!=', '')
                    ->orderBy('billing_no')
                    ->distinct()
                    ->pluck('billing_no'),
            ),
            'van_statuses' => $this->stringColumn(
                DB::table('eirtranfile1')
                    ->whereNotNull('eirstatus')
                    ->where('eirstatus', '!=', '')
                    ->orderBy('eirstatus')
                    ->distinct()
                    ->pluck('eirstatus'),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function rows(array $filters): Collection
    {
        if (! Schema::hasTable('eirtranfile1')) {
            return collect();
        }

        $query = DB::table('eirtranfile1 as f1')
            ->leftJoin('customerfile as cust', 'cust.cuscde', '=', 'f1.cuscde')
            ->leftJoin('consigneefile as cons', 'cons.concde', '=', 'f1.concde')
            ->leftJoin('destinationfile as dest', 'dest.dstcde', '=', 'f1.dstcde')
            ->where('f1.recid', '>', 0)
            ->selectRaw('
                f1.docnum,
                MIN(f1.issue_dte) as issue_dte,
                MAX(f1.issue_time) as issue_time,
                MAX(f1.origin) as origin,
                MAX(f1.vannum) as vannum,
                MAX(cust.cusdsc) as cusdsc,
                MAX(cons.condsc) as condsc,
                MAX(f1.pickup) as pickup,
                MAX(f1.`return`) as `return`,
                MAX(f1.billing_no) as billing_no,
                MAX(f1.accpt_dte) as accpt_dte,
                MAX(f1.eirstatus) as eirstatus
            ')
            ->groupBy('f1.docnum')
            ->orderByDesc('issue_dte');

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
            $query->where('f1.issue_dte', '>=', $from);
        }
        if ($to !== '') {
            $query->where('f1.issue_dte', '<=', $to);
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

        $shipper = trim((string) ($filters['shipper'] ?? ''));
        if ($shipper !== '') {
            $query->where('cust.cusdsc', $shipper);
        }
        $consignee = trim((string) ($filters['consignee'] ?? ''));
        if ($consignee !== '') {
            $query->where('cons.condsc', $consignee);
        }
        $billing = trim((string) ($filters['billing_no'] ?? ''));
        if ($billing !== '') {
            $query->where('f1.billing_no', $billing);
        }
        $status = trim((string) ($filters['van_status'] ?? ''));
        if ($status !== '') {
            $query->where('f1.eirstatus', $status);
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
