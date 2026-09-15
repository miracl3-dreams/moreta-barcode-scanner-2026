<?php

namespace App\Services;

use App\Repositories\EirRegisterRepository;
use DateTime;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EirRegisterService
{
    public function __construct(private EirRegisterRepository $register) {}

    /**
     * @return array{
     *     docnums: list<string>,
     *     shippers: list<string>,
     *     consignees: list<string>,
     *     billing_nos: list<string>,
     *     van_statuses: list<string>
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
        $filename = 'EIR Register_'.now('Asia/Manila')->format('mdY_His').'.xls';

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
        $docnums = $rows->map(fn ($row) => trim((string) ($row->docnum ?? '')))->filter()->values()->all();
        sort($docnums);
        $issueDates = $rows->map(fn ($row) => trim((string) ($row->issue_dte ?? '')))->filter()->values()->all();
        sort($issueDates);

        $fromFilter = trim((string) ($filters['date_from'] ?? ''));
        $toFilter = trim((string) ($filters['date_to'] ?? ''));
        $dateFrom = $fromFilter !== ''
            ? $this->longDate($fromFilter)
            : $this->longDate((string) ($issueDates[0] ?? ''));
        $dateTo = $toFilter !== ''
            ? $this->longDate($toFilter)
            : $this->longDate((string) (end($issueDates) ?: ''));

        $docRange = (string) ($docnums[0] ?? '');
        if (count($docnums) > 1) {
            $docRange .= ' - '.end($docnums);
        }

        $out = $company['name'].$eol;
        $out .= 'EIR FORM REGISTER'.$eol;
        $out .= 'EIR Document Number: '.$docRange.$eol;
        $out .= 'EIR Issue Location (Origin / Destination):'.$eol;
        $out .= 'Van No :'.$eol;
        $out .= 'EIR Status :'.$eol;
        $out .= 'Issuance Date From: '.$dateFrom.($dateTo !== $dateFrom ? ' to '.$dateTo : '').$eol;
        $out .= 'Date Printed : '.now('Asia/Manila')->format('F d, Y').$eol;

        $headers = [
            'TRANSACTION DATE',
            'EIR DOCUMENT NUMBER',
            'EIR ISSUE LOCATION (ORIGIN / DESTINATION)',
            'VAN NUMBER',
            'ISSUE DATE/TIME',
            'SHIPPER',
            'CONSIGNEE',
            'TYPE OF MOVE',
            'BILLING FORM DOCUMENT NUMBER',
            'ACCEPTANCE (RETURN) DATE/TIME',
            'EIR STATUS',
            'DAYS UNRETURNED',
        ];
        foreach ($headers as $header) {
            $out .= $tab.$header;
        }
        $out .= $eol;

        foreach ($rows as $row) {
            $move = '';
            $pickup = trim((string) ($row->pickup ?? ''));
            $return = trim((string) ($row->return ?? ''));
            if ($pickup !== '') {
                $move .= 'PICK UP - '.$pickup;
            }
            if ($return !== '') {
                $move .= 'RETURN - '.$return;
            }

            $cells = [
                (string) ($row->issue_dte ?? ''),
                (string) ($row->docnum ?? ''),
                (string) ($row->origin ?? ''),
                (string) ($row->vannum ?? ''),
                (string) ($row->issue_dte ?? ''),
                (string) ($row->cusdsc ?? ''),
                (string) ($row->condsc ?? ''),
                $move,
                (string) ($row->billing_no ?? ''),
                $this->dateTimeDisplay((string) ($row->accpt_dte ?? '')),
                (string) ($row->eirstatus ?? ''),
                $this->unreturned($row),
            ];
            foreach ($cells as $cell) {
                $out .= $tab.$cell;
            }
            $out .= $eol;
        }

        return $out;
    }

    private function unreturned(object $row): string
    {
        $issueDate = trim((string) ($row->issue_dte ?? ''));
        $issueTime = trim((string) ($row->issue_time ?? ''));
        if ($issueDate === '' || $issueTime === '' || str_starts_with($issueDate, '0000-00-00')) {
            return '';
        }

        try {
            $start = new DateTime($issueDate.' '.$issueTime);
            $accepted = trim((string) ($row->accpt_dte ?? ''));
            $end = $accepted !== '' && ! str_starts_with($accepted, '0000-00-00')
                ? new DateTime($accepted)
                : new DateTime('now', new DateTimeZone('Asia/Manila'));
            $interval = $start->diff($end);
        } catch (\Exception) {
            return '';
        }

        $out = '';
        if ($interval->y) {
            $out .= $interval->y.' yrs ';
        }
        if ($interval->m) {
            $out .= $interval->m.' mons ';
        }
        if ($interval->d) {
            $out .= $interval->d.' day(s) ';
        }
        if ($interval->h) {
            $out .= $interval->h.' hrs ';
        }
        if ($interval->i) {
            $out .= $interval->i.' mins ';
        }

        return $out;
    }

    private function dateTimeDisplay(string $value): string
    {
        $value = trim($value);
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return '';
        }
        $stamp = strtotime($value);

        return $stamp === false ? $value : date('m-d-Y H:i:s', $stamp);
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
