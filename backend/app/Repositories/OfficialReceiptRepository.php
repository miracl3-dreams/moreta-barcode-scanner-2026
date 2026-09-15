<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OfficialReceiptRepository
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
     * @return Collection<int, object>
     */
    public function receipts(string $from, string $to): Collection
    {
        $query = DB::table('arpaymentsfile1')
            ->selectRaw('docnum, MIN(trndte) as trndte, MAX(payee) as payee, MAX(cusdsc) as cusdsc, MAX(condsc) as condsc')
            ->groupBy('docnum')
            ->orderBy('docnum');

        if ($from !== '' && $to !== '') {
            $query->where('docnum', '>=', $from)->where('docnum', '<=', $to);
        }

        return $query->get();
    }

    /**
     * @param  list<string>  $docnums
     * @return array<string, list<array{amtapp: float, evatamt: float, ewtamt: float}>>
     */
    public function applicationsByDocnum(array $docnums): array
    {
        $docnums = array_values(array_unique(array_filter($docnums)));
        if ($docnums === []) {
            return [];
        }

        $rows = DB::table('arpaymentapplication')
            ->whereIn('docnum', $docnums)
            ->get(['docnum', 'amtapp', 'evatamt', 'ewtamt']);

        $map = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row->docnum ?? ''));
            $map[$key][] = [
                'amtapp' => (float) ($row->amtapp ?? 0),
                'evatamt' => (float) ($row->evatamt ?? 0),
                'ewtamt' => (float) ($row->ewtamt ?? 0),
            ];
        }

        return $map;
    }
}
