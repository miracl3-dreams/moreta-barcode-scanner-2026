<?php

namespace App\Services;

use App\Repositories\ShipmentSummaryRepository;
use App\Support\ShipmentSummaryPdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class ShipmentSummaryService
{
    public function __construct(private ShipmentSummaryRepository $summary) {}

    /**
     * @return array{destinations: list<array{dstcde: string, dstdsc: string}>}
     */
    public function lookups(): array
    {
        return [
            'destinations' => $this->summary->destinations(),
        ];
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public function searchShippers(string $term): array
    {
        return $this->summary->searchShippers($term);
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public function searchConsignees(string $term): array
    {
        return $this->summary->searchConsignees($term);
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public function searchVoyages(string $term): array
    {
        return $this->summary->searchVoyages($term);
    }

    public function pdf(
        string $filter,
        string $payee,
        string $cuscde,
        string $concde,
        string $dstcde,
        string $voynum,
        string $from,
        string $to,
    ): Response {
        $payee = $payee === 'consignee' ? 'consignee' : 'shipper';
        $code = $payee === 'consignee' ? trim($concde) : trim($cuscde);
        $fromSql = $this->sqlDate($from);
        $toSql = $this->sqlDate($to);

        $title = match ($filter) {
            'bydte' => 'By Sailing Date',
            'byvoy' => 'By Voyage Number',
            default => 'By Destination',
        };

        $payeeLine = $payee === 'consignee'
            ? 'Consignee : '.$this->summary->consigneeName($code)
            : 'Shipper : '.$this->summary->shipperName($code);

        $periodLine = '';
        if ($filter === 'bydte') {
            $periodLine = 'Date Period from : '.$this->periodLabel($from, $fromSql)
                .' To '.$this->periodLabel($to, $toSql);
        }

        $payers = [];
        foreach ($this->summary->payerCodes($payee, $code) as $payerCode) {
            $bills = $this->summary->bills($payee, $payerCode, $filter, trim($dstcde), trim($voynum), $fromSql, $toSql);
            $payers[] = [
                'groups' => $this->groups($filter, $bills),
            ];
        }

        return new Response(
            ShipmentSummaryPdf::make()->render(
                $this->summary->companyName(),
                $title,
                $payeeLine,
                $periodLine,
                now('Asia/Manila')->format('F j, Y h:i:s A'),
                $payers,
            ),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="shipper-shipment-summary.pdf"',
            ],
        );
    }

    /**
     * @return list<array{label: string, rows: list<array{
     *     date: string, voyage: string, docnum: string, category: string,
     *     van: string, qty: string, description: string, show_bl: bool
     * }>}>
     */
    private function groups(string $filter, Collection $bills): array
    {
        $lines = $this->summary->cargoLines(
            $bills->map(fn ($bill) => trim((string) ($bill->docnum ?? '')))->all(),
        )->groupBy(fn ($line) => trim((string) ($line->docnum ?? '')));

        $grouped = $bills->groupBy(function ($bill) use ($filter) {
            return match ($filter) {
                'bydte' => substr(trim((string) ($bill->trndte ?? '')), 0, 10),
                'byvoy' => trim((string) ($bill->voynum ?? '')),
                default => trim((string) ($bill->dstcde ?? '')),
            };
        });

        $groups = [];
        foreach ($grouped as $key => $groupBills) {
            $label = match ($filter) {
                'bydte' => $this->displayDate($key),
                default => (string) $key,
            };
            $rows = [];
            foreach ($groupBills as $bill) {
                $docnum = trim((string) ($bill->docnum ?? ''));
                $cargo = $lines->get($docnum, collect());
                $first = true;
                foreach ($cargo as $line) {
                    $rows[] = [
                        'date' => $this->displayDate($bill->trndte ?? ''),
                        'voyage' => trim((string) ($bill->voynum ?? '')),
                        'docnum' => $docnum,
                        'category' => trim((string) ($line->catcde ?? '')),
                        'van' => strtoupper(trim((string) ($line->soc ?? ''))) === 'Y'
                            ? trim((string) ($line->socvannum ?? ''))
                            : trim((string) ($line->vannum ?? '')),
                        'qty' => $this->qty($line->itmqty ?? ''),
                        'description' => trim((string) ($line->classdsc ?? '')),
                        'show_bl' => $first,
                    ];
                    $first = false;
                }
            }
            $groups[] = [
                'label' => $label,
                'rows' => $rows,
            ];
        }

        return $groups;
    }

    private function qty(mixed $value): string
    {
        $number = (float) str_replace(',', '', trim((string) ($value ?? '')));
        if ($number <= 0) {
            return '';
        }

        return number_format($number);
    }

    private function sqlDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
            return substr($value, 0, 10);
        }
        $stamp = strtotime($value);

        return $stamp === false ? '' : date('Y-m-d', $stamp);
    }

    private function periodLabel(string $original, string $sql): string
    {
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', trim($original)) === 1) {
            return trim($original);
        }
        if ($sql === '') {
            return trim($original);
        }
        $stamp = strtotime($sql);

        return $stamp === false ? $sql : date('m-d-Y', $stamp);
    }

    private function displayDate(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return '';
        }
        $stamp = strtotime(substr($text, 0, 10));
        if ($stamp === false) {
            return '';
        }

        return date('n-j-Y', $stamp);
    }
}
