<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoadUnloadSheetRepository
{
    public function companyName(): string
    {
        $row = DB::table('companyfile')->first();
        if (! $row) {
            return '';
        }
        if (isset($row->comdsc) && trim((string) $row->comdsc) !== '') {
            return strtoupper((string) $row->comdsc);
        }

        return strtoupper((string) ($row->companydescription ?? ''));
    }

    /**
     * @return array{voynum: string, trndte: string, dstcde: string, origin: string}|null
     */
    public function voyage(string $voynum): ?array
    {
        $row = DB::table('voyagefile')->where('voynum', $voynum)->first(['voynum', 'trndte', 'dstcde', 'origin']);
        if (! $row) {
            return null;
        }

        return [
            'voynum' => trim((string) ($row->voynum ?? '')),
            'trndte' => (string) ($row->trndte ?? ''),
            'dstcde' => trim((string) ($row->dstcde ?? '')),
            'origin' => trim((string) ($row->origin ?? '')),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    public function lines(string $voynum, string $sort): Collection
    {
        if (! Schema::hasTable('billofladingfile2')) {
            return collect();
        }

        $query = DB::table('billofladingfile2 as f2')
            ->leftJoin('billofladingfile1 as f1', function ($join) {
                $join->on('f1.voynum', '=', 'f2.voynum')->on('f1.docnum', '=', 'f2.docnum');
            })
            ->where('f2.voynum', $voynum)
            ->where('f2.trncde', 'BL')
            ->select([
                'f2.docnum',
                'f2.itmcde',
                'f2.class',
                'f2.qty',
                'f2.catcde',
                'f2.soc',
                'f2.socvannum',
                'f2.vannum',
                'f2.sealnum',
                'f2.classdsc',
                'f2.itmqty',
                'f1.cuscde',
                'f1.concde',
                'f1.shipmode',
            ]);

        if ($sort === 'blnum') {
            $query->orderByRaw('lpad(f2.docnum, 50, 0)');
        } elseif ($sort === 'vannum') {
            $query->orderByRaw('lpad(f2.vannum, 50, 0)');
        } else {
            $query->orderBy('f2.docnum');
        }

        return $query->get();
    }
}
