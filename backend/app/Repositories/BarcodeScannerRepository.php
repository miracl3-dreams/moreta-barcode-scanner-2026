<?php

namespace App\Repositories;

use App\Models\EirFormSketch;
use App\Support\LegacySchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BarcodeScannerRepository
{
    public function __construct(private EirFormRepository $eirs) {}

    /**
     * @return list<array{dstcde: string, dstdsc: string}>
     */
    public function destinations(): array
    {
        return $this->eirs->destinations();
    }

    /**
     * Active voyages by sailing date (trndte): upcoming (today or later),
     * plus the latest sailing on or before today so the current trip is listed.
     *
     * @return list<array{voynum: string, trndte: string}>
     */
    public function activeVoyages(): array
    {
        if (! LegacySchema::hasTable('voyagefile')) {
            return [];
        }

        $applyFilters = function ($builder) {
            if (LegacySchema::hasColumn('voyagefile', 'voytype')) {
                $builder->where('voytype', 'Reg');
            }
            $builder->whereRaw("voynum NOT REGEXP 'CF.*CF'");

            return $builder;
        };

        $latestSailing = $applyFilters(DB::table('voyagefile'))
            ->whereRaw('trndte <= CURDATE()')
            ->max('trndte');

        $builder = $applyFilters(DB::table('voyagefile'))
            ->where(function ($query) use ($latestSailing) {
                $query->whereRaw('trndte >= CURDATE()');
                if ($latestSailing !== null && trim((string) $latestSailing) !== '' && ! str_starts_with((string) $latestSailing, '0000-00-00')) {
                    $query->orWhere('trndte', $latestSailing);
                }
            })
            ->orderByDesc('trndte')
            ->orderBy('voynum');

        return $builder
            ->get(['voynum', 'trndte'])
            ->map(fn ($row) => [
                'voynum' => trim((string) ($row->voynum ?? '')),
                'trndte' => substr(trim((string) ($row->trndte ?? '')), 0, 10),
            ])
            ->filter(fn ($row) => $row['voynum'] !== '')
            ->unique('voynum')
            ->values()
            ->all();
    }

    public function voyageIsActive(string $voynum): bool
    {
        $voynum = trim($voynum);
        if ($voynum === '') {
            return false;
        }

        foreach ($this->activeVoyages() as $row) {
            if ($row['voynum'] === $voynum) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return object{
     *     recid: mixed,
     *     docnum: mixed,
     *     pickup: mixed,
     *     return: mixed,
     *     dstcde: mixed,
     *     vannum: mixed,
     *     voynum: mixed,
     *     eirstatus: mixed
     * }|null
     */
    public function findHeaderByDocnum(string $docnum): ?object
    {
        if ($docnum === '') {
            return null;
        }

        $columns = ['recid', 'docnum', 'pickup', 'return', 'dstcde'];
        foreach (['eirstatus', 'vannum', 'voynum', 'cuscde', 'concde', 'origin'] as $column) {
            if (LegacySchema::hasColumn('eirtranfile1', $column)) {
                $columns[] = $column;
            }
        }

        return DB::table('eirtranfile1')->where('docnum', $docnum)->first($columns);
    }

    /**
     * @return Collection<int, EirFormSketch>
     */
    public function sketch(string $docnum): Collection
    {
        return $this->eirs->sketch($docnum);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateHeader(string $docnum, array $attributes): void
    {
        $allowed = ['pickup', 'return', 'dstcde', 'voynum', 'eirstatus', 'eirstatusdte', 'accpt_dte'];
        $payload = [];
        foreach ($allowed as $column) {
            if (! array_key_exists($column, $attributes)) {
                continue;
            }
            if ($column !== 'pickup' && $column !== 'return' && ! LegacySchema::hasColumn('eirtranfile1', $column)) {
                continue;
            }
            $payload[$column] = $attributes[$column];
        }

        if ($payload === []) {
            return;
        }

        DB::table('eirtranfile1')->where('docnum', $docnum)->update($payload);
    }

    /**
     * Van usage (by van number) and van endorsement both read vanfile.lastloc
     * via prevannum. EIR vannum is that container number.
     */
    public function updateVanLastloc(string $vannum, string $lastloc): void
    {
        $vannum = trim($vannum);
        $lastloc = trim($lastloc);
        if ($vannum === '' || $lastloc === '' || ! LegacySchema::hasTable('vanfile')) {
            return;
        }

        $query = DB::table('vanfile')->where(function ($inner) use ($vannum) {
            $inner->where('prevannum', $vannum);
            if (LegacySchema::hasColumn('vanfile', 'vannum')) {
                $inner->orWhere('vannum', $vannum);
            }
        });

        $payload = [];
        if (LegacySchema::hasColumn('vanfile', 'lastloc')) {
            $payload['lastloc'] = $lastloc;
        }
        if ($payload === []) {
            return;
        }

        $query->update($payload);
    }

    public function vanLastloc(string $vannum): string
    {
        $vannum = trim($vannum);
        if ($vannum === '' || ! LegacySchema::hasTable('vanfile') || ! LegacySchema::hasColumn('vanfile', 'lastloc')) {
            return '';
        }

        $row = DB::table('vanfile')
            ->where(function ($inner) use ($vannum) {
                $inner->where('prevannum', $vannum);
                if (LegacySchema::hasColumn('vanfile', 'vannum')) {
                    $inner->orWhere('vannum', $vannum);
                }
            })
            ->first(['lastloc']);

        return trim((string) ($row->lastloc ?? ''));
    }

    /**
     * Container category (vanfile.vandsc), which matches categoryfile.catcde so
     * Van Endorsement can put the van under the right footer column.
     */
    public function vanCategory(string $vannum): string
    {
        $vannum = trim($vannum);
        if ($vannum === '' || ! LegacySchema::hasTable('vanfile') || ! LegacySchema::hasColumn('vanfile', 'vandsc')) {
            return '';
        }

        $row = DB::table('vanfile')
            ->where(function ($inner) use ($vannum) {
                $inner->where('prevannum', $vannum);
                if (LegacySchema::hasColumn('vanfile', 'vannum')) {
                    $inner->orWhere('vannum', $vannum);
                }
            })
            ->first(['vandsc']);

        return trim((string) ($row->vandsc ?? ''));
    }

    /**
     * Copy a barcode location change onto billofladingfile2 so Van Usage
     * (by van number / summary) can list it next to BL / EV / VT rows.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function insertLocationUsage(array $attributes): void
    {
        if (! LegacySchema::hasTable('billofladingfile2')) {
            return;
        }

        $payload = [];
        foreach ($attributes as $column => $value) {
            if (! is_string($column) || $column === '' || ! LegacySchema::hasColumn('billofladingfile2', $column)) {
                continue;
            }
            $payload[$column] = $value;
        }

        if ($payload === [] || trim((string) ($payload['vannum'] ?? '')) === '') {
            return;
        }

        DB::table('billofladingfile2')->insert($payload);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function replaceSketch(string $docnum, array $rows): void
    {
        $this->eirs->replaceSketch($docnum, $rows);
    }
}
