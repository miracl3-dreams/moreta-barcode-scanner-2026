<?php

namespace App\Services;

use App\Repositories\SummaryRepository;
use App\Support\CrudList;
use App\Support\SummaryPdf;
use Illuminate\Http\Response;

class SummaryService
{
    public function __construct(private SummaryRepository $summary) {}

    /**
     * @return array{payment_types: list<array{paytrmcde: string}>, categories: list<array{catcde: string}>}
     */
    public function lookups(): array
    {
        return [
            'payment_types' => $this->summary->paymentTypes(),
            'categories' => $this->summary->categories(),
        ];
    }

    /**
     * @return array{data: mixed, meta: array<string, int>, total_amount: string}
     */
    public function paymentList(
        string $voynum,
        string $paytrmcde,
        string $cusdsc,
        string $condsc,
        mixed $perPage,
    ): array {
        $pageSize = CrudList::perPage($perPage);
        if ($pageSize === 'all') {
            $pageSize = 10;
        }
        if (! in_array($pageSize, [5, 10, 25, 50], true)) {
            $pageSize = 10;
        }

        $paginator = $this->summary->paymentList($voynum, $paytrmcde, $cusdsc, $condsc, $pageSize);
        $voynums = $paginator->getCollection()
            ->map(fn ($row) => trim((string) ($row->voynum ?? '')))
            ->all();
        $voyageRecids = $this->summary->voyageRecids($voynums);
        $data = $paginator->getCollection()->map(function ($row) use ($voyageRecids) {
            $voynum = trim((string) ($row->voynum ?? ''));

            return [
                'recid' => (int) $row->recid,
                'voyage_recid' => $voyageRecids[$voynum] ?? 0,
                'cusdsc' => trim((string) ($row->cusdsc ?? '')),
                'condsc' => trim((string) ($row->condsc ?? '')),
                'voynum' => $voynum,
                'docnum' => trim((string) ($row->docnum ?? '')),
                'paytrmcde' => trim((string) ($row->paytrmcde ?? '')),
                'trntot' => number_format((float) ($row->trntot ?? 0), 2),
            ];
        })->values();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => max(1, $paginator->lastPage()),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'total_amount' => number_format($this->summary->paymentTotal($voynum, $paytrmcde, $cusdsc, $condsc), 2),
        ];
    }

    public function paymentPdf(string $voynum, string $paytrmcde, string $cusdsc, string $condsc): Response
    {
        [$payeeHeader, $payeeField] = $this->payeeColumn($paytrmcde, $cusdsc, $condsc);
        $groups = [];
        foreach ($this->summary->paymentVoynums($voynum, $paytrmcde, $cusdsc, $condsc) as $groupVoynum) {
            $rows = [];
            foreach ($this->summary->paymentBillsForVoyage($groupVoynum, $paytrmcde, $cusdsc, $condsc) as $bill) {
                $rows[] = [
                    'payee' => trim((string) ($bill->{$payeeField} ?? '')),
                    'docnum' => trim((string) ($bill->docnum ?? '')),
                    'shipmode' => trim((string) ($bill->shipmode ?? '')),
                    'trntot' => (float) ($bill->trntot ?? 0),
                ];
            }
            $groups[] = [
                'voynum' => $groupVoynum,
                'rows' => $rows,
            ];
        }

        return new Response(
            SummaryPdf::make()->byPayment(
                $this->summary->companyName(),
                $paytrmcde,
                $payeeHeader,
                now('Asia/Manila')->format('F j, Y'),
                $groups,
            ),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="summary-by-payment-type.pdf"',
            ],
        );
    }

    public function cargoPdf(string $voynum, string $catcde, string $shipmode): Response
    {
        $rows = [];
        foreach ($this->summary->cargoRows($voynum, $catcde, $shipmode) as $row) {
            $soc = strtoupper(trim((string) ($row->soc ?? ''))) === 'Y';
            $vannum = $soc
                ? trim((string) ($row->socvannum ?? ''))
                : trim((string) ($row->vannum ?? ''));
            $rows[] = [
                'docnum' => trim((string) ($row->docnum ?? '')),
                'cuscde' => substr(trim((string) ($row->cuscde ?? '')), 0, 15),
                'concde' => substr(trim((string) ($row->concde ?? '')), 0, 15),
                'vannum' => substr($vannum, 0, 13),
                'weiamt' => number_format((float) ($row->weiamt ?? 0), 2),
                'remarks' => substr(strtolower(trim((string) ($row->remarks ?? ''))), 0, 19),
                'shipmode' => trim((string) ($row->shipmode ?? '')),
            ];
        }

        return new Response(
            SummaryPdf::make()->byCargo(
                $this->summary->companyName(),
                $catcde,
                now('Asia/Manila')->format('F j, Y'),
                $rows,
            ),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="summary-by-cargo-type.pdf"',
            ],
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function payeeColumn(string $paytrmcde, string $cusdsc, string $condsc): array
    {
        $header = strtoupper($paytrmcde) === 'COLLECT' ? 'Consignee' : 'Shipper';
        $field = strtoupper($paytrmcde) === 'COLLECT' ? 'condsc' : 'cusdsc';
        if ($condsc !== '') {
            return ['Consignee', 'condsc'];
        }
        if ($cusdsc !== '') {
            return ['Shipper', 'cusdsc'];
        }

        return [$header, $field];
    }
}
