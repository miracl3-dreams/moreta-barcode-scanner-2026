<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\CollectionReportRepository;
use App\Support\CollectionReportPdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CollectionReportService
{
    public function __construct(private CollectionReportRepository $report) {}

    /**
     * @return array{branch: string, is_supervisor: bool, current_shift: string, shifts: list<array{value: string, label: string}>}
     */
    public function lookups(User $user): array
    {
        $branch = trim((string) ($user->brnchcde ?? ''));
        $supervisor = $user->isAdmin();
        $current = $this->report->currentShift($branch, $supervisor);

        return [
            'branch' => $branch,
            'is_supervisor' => $supervisor,
            'current_shift' => $current,
            'shifts' => $this->shiftOptions($current, $supervisor),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function pdf(array $filters, User $user): Response
    {
        return new Response(
            CollectionReportPdf::make()->render($this->payload($filters, $user, true)),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="collection-report.pdf"',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function export(array $filters, User $user): StreamedResponse
    {
        $text = CollectionReportPdf::exportText($this->payload($filters, $user, false));

        return response()->streamDownload(function () use ($text) {
            echo $text;
        }, 'pdf_collection_reg.xls', [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function shiftOptions(string $current, bool $supervisor): array
    {
        $all = [
            ['value' => '1s', 'label' => 'First Shift'],
            ['value' => '2s', 'label' => 'Second Shift'],
            ['value' => '3s', 'label' => 'Third Shift'],
        ];
        if ($supervisor) {
            return $all;
        }
        if ($current === '1s') {
            return [['value' => '3s', 'label' => 'Third Shift']];
        }
        if ($current === '2s') {
            return [['value' => '1s', 'label' => 'First Shift']];
        }
        if ($current === '3s') {
            return [
                ['value' => '1s', 'label' => 'First Shift'],
                ['value' => '2s', 'label' => 'Second Shift'],
            ];
        }

        return $all;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function payload(array $filters, User $user, bool $truncate): array
    {
        $mode = strtoupper(trim((string) ($filters['par_search'] ?? 'TRNDTE')));
        $includeCancelled = $this->flag($filters['canceldocs'] ?? null);
        $includeZero = $this->flag($filters['includezero'] ?? null);
        $shift = trim((string) ($filters['shiftcde'] ?? ''));
        $branch = trim((string) ($user->brnchcde ?? ''));
        $from = trim((string) ($filters['dtefrom'] ?? ''));
        $to = trim((string) ($filters['dteto'] ?? ''));
        $hrFrom = $this->two((string) ($filters['hrfrom'] ?? '00'));
        $minFrom = $this->two((string) ($filters['minfrom'] ?? '00'));
        $hrTo = $this->two((string) ($filters['hrto'] ?? '00'));
        $minTo = $this->two((string) ($filters['minto'] ?? '00'));

        $byDate = $mode === 'TRNDTE' || $mode === 'TRNDTE_NEW';
        $dateOnly = ($mode === 'TRNDTE' && $shift === '') || $mode === 'CUSCDE' || $mode === 'CONCDE';
        $fromStamp = $from === '' ? '' : ($dateOnly ? $from : $from.' '.$hrFrom.':'.$minFrom.':00');
        $toStamp = $to === '' ? '' : ($dateOnly ? $to : $to.' '.$hrTo.':'.$minTo.':00');

        $query = [
            'from' => $fromStamp,
            'to' => $toStamp,
            'date_only' => $dateOnly,
            'shiftcde' => $shift,
            'branch' => $branch,
        ];
        $title = ' By Date';
        $headers = ['Date', 'O.R. #', 'Voyage #', 'B/L #', 'Shipper / Consignee', 'Amount', ''];
        $widths = [0, 70, 160, 280, 360, 500, 560];
        if ($mode === 'CUSCDE') {
            $title = ' By Shipper ';
            $headers = ['Shipper', 'O.R. #', 'Voyage #', 'B/L #', 'Date', 'Amount', ''];
            $widths = [0, 140, 200, 280, 360, 500, 560];
            $query['payee'] = 'shipper';
            $query['cuscde'] = trim((string) ($filters['cuscde'] ?? ''));
            $query['sort'] = 'cuscde';
        } elseif ($mode === 'CONCDE') {
            $title = ' By Consignee ';
            $headers = ['Consignee', 'O.R. #', 'Voyage #', 'B/L #', 'Date', 'Amount', ''];
            $widths = [0, 140, 200, 280, 360, 500, 560];
            $query['payee'] = 'consignee';
            $query['concde'] = trim((string) ($filters['concde'] ?? ''));
            $query['sort'] = 'concde';
        }

        $payments = $this->report->payments($query);
        $docnums = $payments->map(fn ($row) => trim((string) ($row->docnum ?? '')))->all();
        $tenders = $this->report->tendersByDocnum($docnums);
        $applications = $this->report->applicationsByDocnum($docnums);
        $bolnums = $payments->map(fn ($row) => trim((string) ($row->bolnum ?? '')))->all();
        $docapps = [];
        foreach ($applications as $apps) {
            foreach ($apps as $app) {
                $docapps[] = trim((string) ($app->docapp ?? ''));
            }
        }
        $billsByNum = $this->report->billsByDocnum($bolnums);
        $billsByApp = $this->report->billsByDocapp($docapps);
        $shipperCodes = $payments->map(fn ($row) => trim((string) ($row->cuscde ?? '')))->all();
        $consigneeCodes = $payments->map(fn ($row) => trim((string) ($row->concde ?? '')))->all();
        foreach ($billsByNum as $bill) {
            $shipperCodes[] = trim((string) ($bill->cuscde ?? ''));
            $consigneeCodes[] = trim((string) ($bill->concde ?? ''));
        }
        foreach ($billsByApp as $bill) {
            $shipperCodes[] = trim((string) ($bill->cuscde ?? ''));
            $consigneeCodes[] = trim((string) ($bill->concde ?? ''));
        }
        $shippers = $this->report->shipperNames($shipperCodes);
        $consignees = $this->report->consigneeNames($consigneeCodes);

        $groups = [];
        $grand = 0.0;
        foreach ($payments as $payment) {
            $cancelled = strtoupper(trim((string) ($payment->cancelled ?? ''))) === 'CANCELLED';
            $amount = (float) ($payment->amount ?? 0);
            if (! $includeZero && $amount == 0.0) {
                continue;
            }
            if (! $includeCancelled && $cancelled) {
                continue;
            }
            $docnum = trim((string) ($payment->docnum ?? ''));
            $header = $this->groupHeader($mode, $payment, $shippers, $consignees);
            $remark = $this->remark($tenders[$docnum] ?? []);
            $lines = [];
            $orTotal = 0.0;
            $bolnum = trim((string) ($payment->bolnum ?? ''));
            if ($bolnum !== '') {
                $bill = $billsByNum[$bolnum] ?? null;
                $lineAmount = $amount;
                $lines[] = $this->line($docnum, $bill, $lineAmount, $byDate, $shippers, $consignees, $truncate, null);
                $orTotal += $lineAmount;
            } elseif ($cancelled) {
                $lines[] = [
                    'ornum' => $docnum,
                    'voynum' => '',
                    'bolnum' => '',
                    'col5' => '',
                    'amount' => $amount,
                    'tax' => null,
                ];
            } else {
                foreach ($applications[$docnum] ?? [] as $app) {
                    $lineAmount = (float) ($app->amtappfor ?? 0);
                    if ($lineAmount == 0.0) {
                        $lineAmount = (float) ($app->amtapp ?? 0);
                    }
                    if (! $includeZero && $lineAmount == 0.0) {
                        continue;
                    }
                    $bill = $billsByApp[trim((string) ($app->docapp ?? ''))] ?? null;
                    $tax = (float) ($app->ewtamt ?? 0) + (float) ($app->evatamt ?? 0);
                    $appDate = $this->displayDate((string) ($app->trndte ?? ''));
                    $lines[] = $this->line($docnum, $bill, $lineAmount, $byDate, $shippers, $consignees, $truncate, $appDate, $tax > 0 ? $tax : null);
                    $orTotal += $tax > 0 ? $lineAmount - $tax : $lineAmount;
                }
                if ($lines === []) {
                    $lines[] = [
                        'ornum' => $docnum,
                        'voynum' => '',
                        'bolnum' => '',
                        'col5' => '',
                        'amount' => $amount,
                        'tax' => null,
                    ];
                    $orTotal = $amount;
                }
            }
            $grand += $orTotal;
            $groups[] = [
                'header' => $truncate ? $this->ellipsis($header, 24) : $header,
                'remark' => $remark,
                'total' => $orTotal,
                'lines' => $lines,
            ];
        }

        $expenses = [];
        $expenseTotal = 0.0;
        if ($byDate) {
            foreach ($this->report->expenses($fromStamp, $toStamp, $shift, $branch, $dateOnly) as $row) {
                $price = (float) ($row->untprc ?? 0);
                $desc = trim((string) ($row->itmdsc ?? ''));
                if (! $truncate) {
                    $desc = preg_replace('/[\r\n]+/', ' ', $desc) ?? $desc;
                }
                $expenses[] = [
                    'trndte' => $this->displayDate((string) ($row->trndte ?? '')),
                    'itmdsc' => $desc,
                    'amount' => $price,
                ];
                $expenseTotal += $price;
            }
        }

        $periodFrom = $from === '' ? '' : ($dateOnly ? $from : $from.' '.$hrFrom.':'.$minFrom);
        $periodTo = $to === '' ? '' : ($dateOnly ? $to : $to.' '.$hrTo.':'.$minTo);
        $company = $this->report->company();

        return [
            'company' => $company['name'],
            'title' => 'Collection Report -'.$title,
            'period' => $periodFrom.' - '.$periodTo,
            'shift' => $shift,
            'printed_at' => now('Asia/Manila')->format('F j, Y h:i:s A'),
            'headers' => $headers,
            'widths' => $widths,
            'groups' => $groups,
            'grand' => $grand,
            'expenses' => $expenses,
            'expense_total' => $expenseTotal,
            'net' => $grand - $expenseTotal,
            'show_expenses' => $byDate,
        ];
    }

    /**
     * @param  array<string, string>  $shippers
     * @param  array<string, string>  $consignees
     */
    private function groupHeader(string $mode, object $payment, array $shippers, array $consignees): string
    {
        if ($mode === 'CUSCDE') {
            $code = trim((string) ($payment->cuscde ?? ''));

            return $shippers[$code] ?? trim((string) ($payment->cusdsc ?? $code));
        }
        if ($mode === 'CONCDE') {
            $code = trim((string) ($payment->concde ?? ''));

            return $consignees[$code] ?? trim((string) ($payment->condsc ?? $code));
        }

        return $this->displayDate((string) ($payment->trndte ?? ''));
    }

    /**
     * @param  list<object>  $tenders
     */
    private function remark(array $tenders): string
    {
        $parts = [];
        foreach ($tenders as $tender) {
            $type = trim((string) ($tender->paytyp ?? ''));
            $piece = $type;
            $upper = strtoupper($type);
            if ($upper === 'CHECK') {
                $piece .= ' '.trim((string) ($tender->bnkdsc ?? '')).' '.trim((string) ($tender->chknum ?? '')).' '.$this->displayDate((string) ($tender->chkdte ?? ''));
            } elseif ($upper === 'ONLINE') {
                $piece .= ' '.trim((string) ($tender->bnkdsc ?? '')).' '.$this->displayDate((string) ($tender->chkdte ?? ''));
            }
            $parts[] = $piece;
        }

        return implode(' , ', $parts);
    }

    /**
     * @param  array<string, string>  $shippers
     * @param  array<string, string>  $consignees
     * @return array{ornum: string, voynum: string, bolnum: string, col5: string, amount: float, tax: float|null}
     */
    private function line(
        string $docnum,
        ?object $bill,
        float $amount,
        bool $byDate,
        array $shippers,
        array $consignees,
        bool $truncate,
        ?string $appDate,
        ?float $tax = null,
    ): array {
        $payee = strtolower(trim((string) ($bill->payee ?? '')));
        $name = $payee === 'shipper'
            ? ($shippers[trim((string) ($bill->cuscde ?? ''))] ?? trim((string) ($bill->cusdsc ?? '')))
            : ($consignees[trim((string) ($bill->concde ?? ''))] ?? trim((string) ($bill->condsc ?? '')));
        if ($truncate) {
            $name = substr($name, 0, 20);
        }

        return [
            'ornum' => $docnum,
            'voynum' => trim((string) ($bill->voynum ?? '')),
            'bolnum' => trim((string) ($bill->docnum ?? '')),
            'col5' => $byDate ? $name : ($appDate ?? ''),
            'amount' => $amount,
            'tax' => $tax,
        ];
    }

    private function flag(mixed $value): bool
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'on', 'true', 'yes', 'chkon'], true);
    }

    private function two(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '00';
        }

        return str_pad($value, 2, '0', STR_PAD_LEFT);
    }

    private function displayDate(string $date): string
    {
        $date = trim($date);
        if ($date === '' || str_starts_with($date, '0000-00-00')) {
            return '';
        }
        $stamp = strtotime($date);

        return $stamp === false ? '' : date('n-j-Y', $stamp);
    }

    private function ellipsis(string $text, int $length): string
    {
        return strlen($text) <= $length ? $text : substr($text, 0, $length - 2).'..';
    }
}
