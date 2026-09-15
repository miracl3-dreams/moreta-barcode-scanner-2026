<?php

namespace App\Services;

use App\Repositories\BillingRegisterRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingRegisterService
{
    public function __construct(private BillingRegisterRepository $register) {}

    /**
     * @return array{
     *     docnums: list<string>,
     *     destinations: list<string>,
     *     shippers: list<string>,
     *     consignees: list<string>,
     *     eir_nos: list<string>
     * }
     */
    public function lookups(): array
    {
        return $this->register->lookups();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function export(array $filters): StreamedResponse|JsonResponse
    {
        $rows = $this->register->rows($filters);
        if ($rows->isEmpty()) {
            return response()->json(['message' => 'No Available Transactions'], 422);
        }

        $text = $this->tsv($filters, $rows);
        $filename = 'Billing Register_'.now('Asia/Manila')->format('mdY_His').'.xls';

        return response()->streamDownload(function () use ($text) {
            echo $text;
        }, $filename, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  Collection<int, object>  $rows
     */
    private function tsv(array $filters, Collection $rows): string
    {
        $tab = "\t";
        $eol = "\r\n";
        $company = $this->register->company();
        $docnums = array_values(array_unique(
            $rows->map(fn ($row) => trim((string) ($row->docnum ?? '')))->filter()->all(),
        ));
        sort($docnums);
        $trnDates = array_values(array_unique(
            $rows->map(fn ($row) => trim((string) ($row->trndte ?? '')))->filter()->all(),
        ));
        sort($trnDates);

        $fromFilter = trim((string) ($filters['date_from'] ?? ''));
        $toFilter = trim((string) ($filters['date_to'] ?? ''));
        $dateFrom = $fromFilter !== ''
            ? $this->longDate($fromFilter)
            : $this->longDate((string) ($trnDates[0] ?? ''));
        $dateTo = $toFilter !== ''
            ? $this->longDate($toFilter)
            : $this->longDate((string) (end($trnDates) ?: ''));

        $docRange = (string) ($docnums[0] ?? '');
        if (count($docnums) > 1) {
            $docRange .= ' - '.end($docnums);
        }

        $out = $company['name'].$eol;
        $out .= 'Billing FORM REGISTER'.$eol;
        $out .= 'Billing Document Number: '.$docRange.$eol;
        $out .= 'Destination:'.$eol;
        $out .= 'Issuance Date From: '.$dateFrom.($dateTo !== $dateFrom ? ' to '.$dateTo : '').$eol;
        $out .= 'Date Printed :'.now('Asia/Manila')->format('F d, Y').$eol;

        $headers = [
            'TRANSACTION DATE',
            'DATE DELIVERED',
            'CONTROL / BILLING NUMBER',
            'DESTINATION',
            'SHIPPER',
            'CONSIGNEE',
            'WEIGHT (Excluding Van Weight)',
            'MEASUREMENT',
            'QTY',
            'UNIT',
            'DESCRIPTION OF CARGO',
            'DECLARED VALUE',
            'VAN NUMBER',
            'EIR NUMBER',
        ];
        foreach ($headers as $header) {
            $out .= $tab.$header;
        }
        $out .= $eol;

        foreach ($rows as $row) {
            $cells = [
                (string) ($row->trndte ?? ''),
                (string) ($row->del_dte ?? ''),
                (string) ($row->docnum ?? ''),
                (string) ($row->dstdsc ?? ''),
                (string) ($row->cusdsc ?? ''),
                (string) ($row->condsc ?? ''),
                (string) ($row->weight ?? ''),
                (string) ($row->measurement ?? ''),
                (string) ($row->qty ?? ''),
                (string) ($row->unit ?? ''),
                (string) ($row->itmdesc ?? ''),
                number_format((float) ($row->value ?? 0), 2),
                (string) ($row->vannum ?? ''),
                (string) ($row->eir_no ?? ''),
            ];
            foreach ($cells as $cell) {
                $out .= $tab.$cell;
            }
            $out .= $eol;
        }

        return $out;
    }

    private function longDate(string $date): string
    {
        $date = trim($date);
        if ($date === '' || str_starts_with($date, '0000-00-00')) {
            return '';
        }
        $stamp = strtotime($date);

        return $stamp === false ? '' : date('F d, Y', $stamp);
    }
}
