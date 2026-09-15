<?php

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SummaryRepository
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
     * @return list<array{paytrmcde: string}>
     */
    public function paymentTypes(): array
    {
        return DB::table('paymenttermfile')
            ->orderBy('paytrmcde')
            ->pluck('paytrmcde')
            ->map(fn ($value) => ['paytrmcde' => trim((string) $value)])
            ->filter(fn ($row) => $row['paytrmcde'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array{catcde: string}>
     */
    public function categories(): array
    {
        return DB::table('categoryfile')
            ->orderBy('catcde')
            ->pluck('catcde')
            ->map(fn ($value) => ['catcde' => trim((string) $value)])
            ->filter(fn ($row) => $row['catcde'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return LengthAwarePaginator<int, object>
     */
    public function paymentList(
        string $voynum,
        string $paytrmcde,
        string $cusdsc,
        string $condsc,
        int $perPage,
    ): LengthAwarePaginator {
        $query = DB::table('billofladingfile1')
            ->select(['recid', 'cusdsc', 'condsc', 'voynum', 'docnum', 'paytrmcde', 'trntot']);
        $this->applyPaymentFilters($query, $voynum, $paytrmcde, $cusdsc, $condsc);

        return $query->orderBy('cusdsc')->paginate($perPage);
    }

    public function paymentTotal(string $voynum, string $paytrmcde, string $cusdsc, string $condsc): float
    {
        $query = DB::table('billofladingfile1');
        $this->applyPaymentFilters($query, $voynum, $paytrmcde, $cusdsc, $condsc);

        return (float) $query->sum('trntot');
    }

    /**
     * @return list<string>
     */
    public function paymentVoynums(string $voynum, string $paytrmcde, string $cusdsc, string $condsc): array
    {
        $query = DB::table('billofladingfile1')->select('voynum');
        $this->applyPaymentFilters($query, $voynum, $paytrmcde, $cusdsc, $condsc);

        return $query->groupBy('voynum')
            ->orderBy('voynum')
            ->pluck('voynum')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, object>
     */
    public function paymentBillsForVoyage(
        string $voynum,
        string $paytrmcde,
        string $cusdsc,
        string $condsc,
    ): Collection {
        $query = DB::table('billofladingfile1')->where('voynum', $voynum);
        if ($cusdsc !== '') {
            $query->where('cusdsc', $cusdsc);
        }
        if ($condsc !== '') {
            $query->where('condsc', $condsc);
        }
        if ($paytrmcde !== '') {
            $query->where('paytrmcde', $paytrmcde);
        }

        return $query->orderBy('docnum')->get(['cusdsc', 'condsc', 'docnum', 'shipmode', 'trntot']);
    }

    /**
     * @param  list<string>  $voynums
     * @return array<string, int>
     */
    public function voyageRecids(array $voynums): array
    {
        $voynums = array_values(array_unique(array_filter($voynums)));
        if ($voynums === []) {
            return [];
        }

        $map = [];
        foreach (array_chunk($voynums, 500) as $chunk) {
            $rows = DB::table('voyagefile')
                ->select(['voynum', DB::raw('MIN(recid) as recid')])
                ->whereIn('voynum', $chunk)
                ->groupBy('voynum')
                ->get();
            foreach ($rows as $row) {
                $map[trim((string) ($row->voynum ?? ''))] = (int) $row->recid;
            }
        }

        return $map;
    }

    /**
     * @return Collection<int, object>
     */
    public function cargoRows(string $voynum, string $catcde, string $shipmode): Collection
    {
        $query = DB::table('voyagefile as v')
            ->join('billofladingfile2 as b2', 'v.voynum', '=', 'b2.voynum')
            ->join('billofladingfile1 as b1', 'b2.docnum', '=', 'b1.docnum')
            ->select([
                'v.voynum',
                'b2.docnum',
                'b2.catcde',
                'b2.weiamt',
                'b2.vannum',
                'b2.soc',
                'b2.socvannum',
                'b1.cuscde',
                'b1.concde',
                'b1.shipmode',
                'b1.remarks',
            ]);
        if ($voynum !== '') {
            $query->where('v.voynum', $voynum);
        }
        if ($catcde !== '') {
            $query->where('b2.catcde', $catcde);
        }
        if ($shipmode !== '') {
            $query->where('b1.shipmode', $shipmode);
        }

        return $query->orderBy('v.voynum')->orderBy('b2.catcde')->orderBy('b2.docnum')->get();
    }

    private function applyPaymentFilters(
        Builder $query,
        string $voynum,
        string $paytrmcde,
        string $cusdsc,
        string $condsc,
    ): void {
        if ($voynum !== '') {
            $query->where('voynum', $voynum);
        }
        if ($paytrmcde !== '') {
            $query->where('paytrmcde', $paytrmcde);
        }
        if ($cusdsc !== '') {
            $query->where('cusdsc', $cusdsc);
        }
        if ($condsc !== '') {
            $query->where('condsc', $condsc);
        }
    }
}
