<?php

namespace App\Repositories;

use App\Models\Voyage;
use App\Support\MenuPermission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VoyageRepository
{
    /**
     * @return LengthAwarePaginator<int, Voyage>
     */
    public function paginateForList(string $search, int $perPage): LengthAwarePaginator
    {
        return $this->filteredQuery($search)->paginate($perPage);
    }

    /**
     * @return Collection<int, Voyage>
     */
    public function getForList(string $search): Collection
    {
        return $this->filteredQuery($search)->get();
    }

    public function findByVoynum(string $voynum): ?Voyage
    {
        return Voyage::query()->api()->where('voynum', $voynum)->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Voyage
    {
        return Voyage::query()->create($attributes);
    }

    public function delete(Voyage $voyage): void
    {
        $voyage->delete();
    }

    public function deleteByVoynum(string $voynum): void
    {
        Voyage::query()->where('voynum', $voynum)->delete();
    }

    public function destinationDescription(string $dstcde): string
    {
        $row = DB::table('destinationfile')->where('dstcde', $dstcde)->first(['dstdsc']);

        return trim((string) ($row->dstdsc ?? ''));
    }

    /**
     * @return list<array{vsslcde: string, vssldsc: string}>
     */
    public function vesselOptions(): array
    {
        return DB::table('vesselfile')
            ->orderBy('vsslcde')
            ->get(['vsslcde', 'vssldsc'])
            ->map(fn ($row) => [
                'vsslcde' => trim((string) ($row->vsslcde ?? '')),
                'vssldsc' => trim((string) ($row->vssldsc ?? '')),
            ])
            ->filter(fn ($row) => $row['vsslcde'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array{dstcde: string, dstdsc: string}>
     */
    public function destinationOptions(): array
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
     * @return array{allow_add: bool, allow_edit: bool, allow_delete: bool, allow_view: bool, allow_rates: bool, allow_print: bool}|null
     */
    public function menuPermissions(string $usrcde): ?array
    {
        return MenuPermission::forUserCode($usrcde, 'view_bol.php');
    }

    /**
     * Unlock seals used by BL lines on this voyage (PHP FreeSealNumber).
     */
    public function unlockSealsForVoyage(string $voynum): void
    {
        $seals = DB::table('billofladingfile2')
            ->where('voynum', $voynum)
            ->where('trncde', 'BL')
            ->whereNotNull('sealnum')
            ->where('sealnum', '!=', '')
            ->pluck('sealnum');

        foreach ($seals as $sealnum) {
            $seal = trim((string) $sealnum);
            if ($seal === '') {
                continue;
            }

            DB::table('sealfile')
                ->where('sealnum', $seal)
                ->update([
                    'islock' => 'N',
                    'docnum' => '',
                ]);
        }
    }

    public function deleteBillsByVoynum(string $voynum): void
    {
        DB::table('billofladingfile1')->where('voynum', $voynum)->delete();
        DB::table('billofladingfile2')->where('voynum', $voynum)->delete();
    }

    /**
     * Lock the voyage row, allocate the next BL number, and return the document number.
     */
    public function allocateDocnum(string $voynum): string
    {
        $row = DB::table('voyagefile')->where('voynum', $voynum)->lockForUpdate()->first();
        if ($row === null) {
            return '';
        }

        DB::table('voyagefile')->where('voynum', $voynum)->update(['islock' => 'Y']);

        $prefix = preg_replace('/[^a-z0-9]/i', '', $voynum) ?? '';
        $suffix = trim((string) ($row->bolnum ?? ''));
        if ($suffix === '') {
            $suffix = '001';
        }

        $docnum = $prefix.$suffix;
        $next = $this->nextBolnum($suffix);

        DB::table('voyagefile')->where('voynum', $voynum)->update([
            'bolnum' => $next,
            'islock' => 'N',
        ]);

        return $docnum;
    }

    /**
     * @param  array{trndte?: string|null, etadte?: string|null}  $dates
     */
    public function updateSailingDates(string $voynum, array $dates): void
    {
        $voyage = [];
        $header = [];
        $lines = [];

        if (array_key_exists('trndte', $dates) && $dates['trndte'] !== null && $dates['trndte'] !== '') {
            $voyage['trndte'] = $dates['trndte'];
            $header['trndte'] = $dates['trndte'];
            $lines['trndte'] = $dates['trndte'];
        }
        if (array_key_exists('etadte', $dates) && $dates['etadte'] !== null && $dates['etadte'] !== '') {
            $voyage['etadte'] = $dates['etadte'];
            $header['etadte'] = $dates['etadte'];
        }

        if ($voyage !== []) {
            DB::table('voyagefile')->where('voynum', $voynum)->update($voyage);
        }
        if ($header !== []) {
            DB::table('billofladingfile1')->where('voynum', $voynum)->update($header);
        }
        if ($lines !== []) {
            DB::table('billofladingfile2')->where('voynum', $voynum)->update($lines);
        }
    }

    private function nextBolnum(string $current): string
    {
        $width = max(strlen($current), 3);
        if (! ctype_digit($current)) {
            $digits = preg_replace('/\D/', '', $current) ?: '1';

            return str_pad((string) ((int) $digits + 1), min($width, 5), '0', STR_PAD_LEFT);
        }

        return str_pad((string) ((int) $current + 1), min($width, 5), '0', STR_PAD_LEFT);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Voyage>
     */
    private function filteredQuery(string $search)
    {
        // webmoreta pager has no ORDER BY; InnoDB returns voyagefile by recid ASC.
        $query = Voyage::query()->api()->orderBy('recid');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('voynum', 'like', $like)
                    ->orWhere('vsslcde', 'like', $like)
                    ->orWhere('origin', 'like', $like)
                    ->orWhere('dstcde', 'like', $like);
            });
        }

        return $query;
    }
}
