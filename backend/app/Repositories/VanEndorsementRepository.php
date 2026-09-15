<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VanEndorsementRepository
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
     * @return array{recid: int, voynum: string, vsslcde: string, trndte: string, origin: string, dstdsc: string}|null
     */
    public function voyageByRecid(int $recid): ?array
    {
        $row = DB::table('voyagefile')->where('recid', $recid)->first([
            'recid',
            'voynum',
            'vsslcde',
            'trndte',
            'origin',
            'dstdsc',
        ]);
        if (! $row) {
            return null;
        }

        return [
            'recid' => (int) ($row->recid ?? 0),
            'voynum' => trim((string) ($row->voynum ?? '')),
            'vsslcde' => trim((string) ($row->vsslcde ?? '')),
            'trndte' => trim((string) ($row->trndte ?? '')),
            'origin' => trim((string) ($row->origin ?? '')),
            'dstdsc' => trim((string) ($row->dstdsc ?? '')),
        ];
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

    /**
     * @return Collection<int, object>
     */
    public function cargoLines(string $voynum, string $trncde, string $catLike): Collection
    {
        return DB::table('billofladingfile2')
            ->where('voynum', $voynum)
            ->where('trncde', $trncde)
            ->where('catcde', 'like', $catLike)
            ->orderBy('recid')
            ->get(['vannum', 'classdsc', 'catcde', 'recid']);
    }

    /**
     * @param  list<string>  $vanNumbers
     * @return array<string, string>
     */
    public function vanLocations(array $vanNumbers): array
    {
        $vanNumbers = array_values(array_unique(array_filter($vanNumbers)));
        if ($vanNumbers === []) {
            return [];
        }

        $rows = DB::table('vanfile')
            ->whereIn('prevannum', $vanNumbers)
            ->get(['prevannum', 'lastloc']);

        $map = [];
        foreach ($rows as $row) {
            $van = trim((string) ($row->prevannum ?? ''));
            if ($van === '' || isset($map[$van])) {
                continue;
            }
            $map[$van] = trim((string) ($row->lastloc ?? ''));
        }

        return $map;
    }
}
