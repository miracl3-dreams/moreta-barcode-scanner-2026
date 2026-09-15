<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class VanUsageRepository
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
    public function searchVans(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        return DB::table('vanfile')
            ->where('prevannum', 'like', $term.'%')
            ->where('remove', 'N')
            ->orderBy('vandsc')
            ->limit(5)
            ->get(['prevannum', 'vandsc'])
            ->map(fn ($row) => [
                'code' => trim((string) ($row->prevannum ?? '')),
                'label' => trim((string) ($row->prevannum ?? '')),
            ])
            ->filter(fn ($row) => $row['code'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{prevannum: string, lastloc: string}|null
     */
    public function findVan(string $prevannum): ?array
    {
        $prevannum = trim($prevannum);
        if ($prevannum === '') {
            return null;
        }

        $row = DB::table('vanfile')
            ->where('prevannum', $prevannum)
            ->first(['prevannum', 'lastloc']);

        if (! $row) {
            return null;
        }

        return [
            'prevannum' => trim((string) ($row->prevannum ?? '')),
            'lastloc' => trim((string) ($row->lastloc ?? '')),
        ];
    }

    /**
     * @return LazyCollection<int, object>
     */
    public function summaryCursor(string $from, string $to, bool $skipSoc): LazyCollection
    {
        $query = DB::table('billofladingfile2')
            ->select([
                'vannum',
                'soc',
                'socvannum',
                'trndte',
                'origin',
                'dstcde',
                'docnum',
                'voynum',
                'trncde',
                'vt_status',
                'payee',
                'cuscde',
                'concde',
            ])
            ->orderByDesc('trndte');

        if ($from !== '') {
            $query->where('trndte', '>=', $from);
        }
        if ($to !== '') {
            $query->where('trndte', '<=', $to);
        }
        if ($skipSoc) {
            $query->where(function ($inner) {
                $inner->whereNull('soc')->orWhere('soc', '<>', 'Y');
            });
        }

        return $query->cursor();
    }

    /**
     * @return Collection<int, object>
     */
    public function locationRows(string $origin): Collection
    {
        $query = DB::table('vanfile')
            ->select(['prevannum', 'vandsc', 'remarks', 'lastloc'])
            ->orderBy('lastloc')
            ->orderBy('prevannum');

        if ($origin !== '') {
            $query->where('lastloc', $origin);
        }

        $vans = $query->get();
        $latest = $this->latestRegularBills($vans->pluck('prevannum')->all());

        return $vans->map(function ($van) use ($latest) {
            $key = trim((string) ($van->prevannum ?? ''));
            $bill = $latest[$key] ?? null;

            return (object) [
                'lastloc' => trim((string) ($van->lastloc ?? '')),
                'prevannum' => $key,
                'vandsc' => trim((string) ($van->vandsc ?? '')),
                'remarks' => trim((string) ($van->remarks ?? '')),
                'voynum' => trim((string) ($bill->voynum ?? '')),
                'cuscde' => trim((string) ($bill->cuscde ?? '')),
            ];
        });
    }

    /**
     * @return LazyCollection<int, object>
     */
    public function byVanNumberCursor(string $vannum, string $from, string $to): LazyCollection
    {
        $query = DB::table('billofladingfile2 as bl')
            ->leftJoin('voyagefile as voy', 'voy.voynum', '=', 'bl.voynum')
            ->select([
                'bl.trndte',
                'bl.voynum',
                'bl.docnum',
                'bl.cuscde',
                'bl.concde',
                'bl.trncde',
                'bl.vt_status',
                'bl.payee',
                'bl.origin',
                'bl.dstcde',
                'voy.origin as voy_origin',
                'voy.dstcde as voy_dstcde',
            ])
            ->where('bl.vannum', $vannum)
            ->orderByDesc('bl.trndte');

        if ($from !== '' && $to !== '') {
            $query->where('bl.trndte', '>=', $from)->where('bl.trndte', '<=', $to);
        }

        return $query->cursor();
    }

    /**
     * @param  list<mixed>  $vannums
     * @return array<string, object>
     */
    private function latestRegularBills(array $vannums): array
    {
        $map = [];
        $keys = array_values(array_unique(array_filter(array_map(
            static fn ($value) => trim((string) $value),
            $vannums,
        ))));

        foreach (array_chunk($keys, 1000) as $chunk) {
            $rows = DB::table('billofladingfile2')
                ->select(['vannum', 'voynum', 'cuscde', 'trndte', 'recid'])
                ->where('voytyp', 'Reg')
                ->whereIn('vannum', $chunk)
                ->orderByDesc('trndte')
                ->orderByDesc('recid')
                ->get();

            foreach ($rows as $row) {
                $key = trim((string) ($row->vannum ?? ''));
                if ($key !== '' && ! isset($map[$key])) {
                    $map[$key] = $row;
                }
            }
        }

        return $map;
    }
}
