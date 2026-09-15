<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CollectionReportRepository
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

    public function currentShift(string $branch, bool $supervisor): string
    {
        if (! Schema::hasTable('shift_branchfile')) {
            return '';
        }
        $query = DB::table('shift_branchfile')->where('branchcde', $branch);
        if (! $supervisor) {
            $today = now('Asia/Manila')->format('Y-m-d');
            $query->where('shiftdate', 'like', '%'.$today.'%')->orderByDesc('shiftdate');
        }
        $row = $query->first(['shiftcde']);

        return trim((string) ($row->shiftcde ?? ''));
    }

    /**
     * @param  array{
     *     from?: string,
     *     to?: string,
     *     date_only?: bool,
     *     shiftcde?: string,
     *     branch?: string,
     *     payee?: string,
     *     cuscde?: string,
     *     concde?: string,
     *     include_cancelled?: bool
     * }  $filters
     * @return Collection<int, object>
     */
    public function payments(array $filters): Collection
    {
        $query = DB::table('arpaymentsfile1')->select([
            'docnum',
            'trndte',
            'amount',
            'bolnum',
            'cancelled',
            'payee',
            'cuscde',
            'concde',
            'cusdsc',
            'condsc',
            'shiftcde',
            'brnchcde',
        ]);

        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));
        $dateOnly = ! empty($filters['date_only']);
        if ($from !== '') {
            if ($dateOnly) {
                $query->whereRaw('DATE(trndte) >= ?', [$from]);
            } else {
                $query->where('trndte', '>=', $from);
            }
        }
        if ($to !== '') {
            if ($dateOnly) {
                $query->whereRaw('DATE(trndte) <= ?', [$to]);
            } else {
                $query->where('trndte', '<=', $to);
            }
        }
        $shift = trim((string) ($filters['shiftcde'] ?? ''));
        if ($shift !== '') {
            $query->where('shiftcde', $shift);
        }
        $branch = trim((string) ($filters['branch'] ?? ''));
        if ($branch !== '') {
            $query->where('brnchcde', $branch);
        }
        $payee = trim((string) ($filters['payee'] ?? ''));
        if ($payee !== '') {
            $query->where('payee', $payee);
        }
        $cuscde = trim((string) ($filters['cuscde'] ?? ''));
        if ($cuscde !== '') {
            $query->where('cuscde', $cuscde);
        }
        $concde = trim((string) ($filters['concde'] ?? ''));
        if ($concde !== '') {
            $query->where('concde', $concde);
        }

        $sort = (string) ($filters['sort'] ?? 'docnum');
        if ($sort === 'cuscde') {
            $query->orderBy('cuscde')->orderBy('trndte')->orderBy('docnum');
        } elseif ($sort === 'concde') {
            $query->orderBy('concde')->orderBy('trndte')->orderBy('docnum');
        } else {
            $query->orderBy('docnum')->orderBy('trndte');
        }

        return $query->get();
    }

    /**
     * @param  list<string>  $docnums
     * @return array<string, list<object>>
     */
    public function tendersByDocnum(array $docnums): array
    {
        $docnums = array_values(array_unique(array_filter($docnums)));
        if ($docnums === [] || ! Schema::hasTable('arpaymentsfile2')) {
            return [];
        }

        $rows = DB::table('arpaymentsfile2')
            ->whereIn('docnum', $docnums)
            ->orderBy('paytyp')
            ->get(['docnum', 'paytyp', 'bnkdsc', 'chknum', 'chkdte']);

        $map = [];
        foreach ($rows as $row) {
            $map[trim((string) ($row->docnum ?? ''))][] = $row;
        }

        return $map;
    }

    /**
     * @param  list<string>  $docnums
     * @return array<string, list<object>>
     */
    public function applicationsByDocnum(array $docnums): array
    {
        $docnums = array_values(array_unique(array_filter($docnums)));
        if ($docnums === []) {
            return [];
        }

        $rows = DB::table('arpaymentapplication')
            ->whereIn('docnum', $docnums)
            ->get(['docnum', 'docapp', 'amtapp', 'amtappfor', 'ewtamt', 'evatamt', 'trndte', 'chknum', 'bnkcde']);

        $map = [];
        foreach ($rows as $row) {
            $map[trim((string) ($row->docnum ?? ''))][] = $row;
        }

        return $map;
    }

    /**
     * @param  list<string>  $docnums
     * @return array<string, object>
     */
    public function billsByDocnum(array $docnums): array
    {
        $docnums = array_values(array_unique(array_filter($docnums)));
        if ($docnums === []) {
            return [];
        }

        $rows = DB::table('billofladingfile1')
            ->whereIn('docnum', $docnums)
            ->where('trncde', 'BL')
            ->get(['docnum', 'docapp', 'voynum', 'payee', 'cuscde', 'concde', 'cusdsc', 'condsc', 'ewtamt', 'evatamt']);

        $map = [];
        foreach ($rows as $row) {
            $map[trim((string) ($row->docnum ?? ''))] = $row;
        }

        return $map;
    }

    /**
     * @param  list<string>  $docapps
     * @return array<string, object>
     */
    public function billsByDocapp(array $docapps): array
    {
        $docapps = array_values(array_unique(array_filter($docapps)));
        if ($docapps === []) {
            return [];
        }

        $rows = DB::table('billofladingfile1')
            ->whereIn('docapp', $docapps)
            ->where('trncde', 'BL')
            ->get(['docnum', 'docapp', 'voynum', 'payee', 'cuscde', 'concde', 'cusdsc', 'condsc', 'ewtamt', 'evatamt']);

        $map = [];
        foreach ($rows as $row) {
            $map[trim((string) ($row->docapp ?? ''))] = $row;
        }

        return $map;
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

    /**
     * @return Collection<int, object>
     */
    public function expenses(string $from, string $to, string $shift, string $branch, bool $dateOnly): Collection
    {
        if (! Schema::hasTable('receivingfile2')) {
            return collect();
        }

        $query = DB::table('receivingfile2')->select(['trndte', 'itmdsc', 'untprc', 'docnum']);
        if ($from !== '') {
            if ($dateOnly) {
                $query->whereRaw('DATE(trndte) >= ?', [$from]);
            } else {
                $query->where('trndte', '>=', $from);
            }
        }
        if ($to !== '') {
            if ($dateOnly) {
                $query->whereRaw('DATE(trndte) <= ?', [$to]);
            } else {
                $query->where('trndte', '<=', $to);
            }
        }
        if ($shift !== '') {
            $query->where('shiftcde', $shift);
        }
        if ($branch !== '') {
            $query->where('brnchcde', $branch);
        }

        return $query->orderBy('docnum')->get();
    }
}
