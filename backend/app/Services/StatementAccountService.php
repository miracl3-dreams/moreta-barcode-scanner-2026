<?php

namespace App\Services;

use App\Repositories\StatementAccountRepository;
use App\Support\StatementAccountPdf;
use DateInterval;
use DateTime;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatementAccountService
{
    /** @var array<string, bool> */
    private array $voyageExists = [];

    /** @var array<string, int> */
    private array $voyageLikeCounts = [];

    public function __construct(private StatementAccountRepository $accounts) {}

    /**
     * @return array{destinations: list<array{dstcde: string, dstdsc: string}>}
     */
    public function lookups(): array
    {
        return ['destinations' => $this->accounts->destinations()];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function byScPdf(array $filters): Response
    {
        return $this->pdfResponse(
            StatementAccountPdf::folioLandscape()->byShipperConsignee($this->byScPayload($filters)),
            'statement-account-bysc.pdf',
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function byScExport(array $filters): StreamedResponse
    {
        return $this->tsv(
            'pdf_statement_account_bysc.xls',
            StatementAccountPdf::byScExport($this->byScPayload($filters)),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function byVoyagePdf(array $filters): Response
    {
        return $this->pdfResponse(
            StatementAccountPdf::letterLandscape()->byVoyage($this->byVoyagePayload($filters)),
            'statement-account-byvoyage.pdf',
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function byVoyageExport(array $filters): StreamedResponse
    {
        return $this->tsv(
            'pdf_statement_account_byvoyage.xls',
            StatementAccountPdf::byVoyageExport($this->byVoyagePayload($filters)),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function byVoyageNewPdf(array $filters): Response
    {
        return $this->pdfResponse(
            StatementAccountPdf::letterLandscape()->byVoyageNew($this->byVoyageNewPayload($filters)),
            'statement-account-byvoyage-new.pdf',
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function outstandingPdf(array $filters): Response
    {
        return $this->pdfResponse(
            StatementAccountPdf::letterLandscape()->allOutstanding($this->outstandingPayload($filters)),
            'statement-account-outstanding.pdf',
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function outstandingExport(array $filters): StreamedResponse
    {
        return $this->tsv(
            'pdf_statement_account_allOutstanding.xls',
            StatementAccountPdf::outstandingExport($this->outstandingPayload($filters)),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function byScPayload(array $filters): array
    {
        $payee = $this->payee($filters);
        $code = $payee === 'consignee'
            ? trim((string) ($filters['concde'] ?? ''))
            : trim((string) ($filters['cuscde'] ?? ''));
        $name = $payee === 'consignee'
            ? trim((string) ($filters['condsc'] ?? ''))
            : trim((string) ($filters['cusdsc'] ?? ''));
        $voynum = strtoupper(trim((string) ($filters['voynum'] ?? ''))) === 'ALL'
            ? ''
            : trim((string) ($filters['voynum'] ?? ''));
        $paid = $this->flag($filters['withors'] ?? null);
        $unpaid = $this->flag($filters['withoutors'] ?? null);
        $incMain = $this->flag($filters['inc_main'] ?? null);
        $incCf = $this->flag($filters['inc_cf'] ?? null);
        $from = trim((string) ($filters['dtefrom'] ?? ''));
        $to = trim((string) ($filters['dteto'] ?? ''));

        $all = ($paid && $unpaid) || (! $paid && ! $unpaid);
        $subtitle = $all
            ? '(Per Shipper/Consignee) ALL'
            : ($paid ? '(Per Shipper/Consignee) with ORS' : '(Per Shipper/Consignee) without ORS');

        $bills = $this->accounts->bills([
            'voynum' => $voynum,
            'from' => $from,
            'to' => $to,
            'payee' => $payee,
            'code' => $code,
            'paid' => $paid && ! $unpaid,
            'sort' => 'trndte,voynum,docnum',
        ]);
        $rows = $this->mapBills($bills, 'trndte');
        $filtered = [];
        foreach ($rows as $row) {
            if (! $this->includeBySc($row['voynum'], $incMain, $incCf)) {
                continue;
            }
            if ($unpaid && ! $paid && $row['balance'] < 1) {
                continue;
            }
            $filtered[] = $row;
        }

        $period = '';
        if ($from !== '') {
            $period = $from;
            $period .= ' TO ';
            $period .= $to;
        }

        $company = $this->accounts->company();

        return [
            'company' => $company['name'],
            'add1' => $company['add1'],
            'add2' => $company['add2'],
            'subtitle' => $subtitle,
            'period' => $period,
            'payee_name' => $name,
            'printed_at' => now('Asia/Manila')->format('m/d/Y h:i A'),
            'rows' => $filtered,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function byVoyagePayload(array $filters): array
    {
        $voynum = trim((string) ($filters['voynum'] ?? ''));
        $paytyp = strtoupper(trim((string) ($filters['paytyp'] ?? '')));
        $sort = 'docnum,voynum,trndte';
        if ($paytyp === 'COLLECT') {
            $sort = 'concde';
        } elseif ($paytyp === 'ON ACCOUNT') {
            $sort = 'cuscde';
        }

        $bills = $this->accounts->bills([
            'voynum' => $voynum,
            'paytyp' => $paytyp,
            'sort' => $sort,
        ]);
        $company = $this->accounts->company();
        $first = $bills->first();

        return [
            'company' => $company['name'],
            'add1' => $company['add1'],
            'add2' => $company['add2'],
            'subtitle' => 'VOYAGE NO. : '.$voynum.'  PAYMENT MODE : '.$paytyp,
            'boldte' => trim((string) ($first->boldte ?? '')),
            'printed_at' => now('Asia/Manila')->format('m/d/Y'),
            'rows' => $this->mapBills($bills, 'trndte'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function byVoyageNewPayload(array $filters): array
    {
        $voynum = trim((string) ($filters['voynum'] ?? ''));
        $paytyp = strtoupper(trim((string) ($filters['paytyp'] ?? '')));
        $sort = 'docnum';
        if ($paytyp === 'COLLECT') {
            $sort = 'concde';
        } elseif ($paytyp === 'ON ACCOUNT') {
            $sort = 'cuscde';
        }

        $bills = $this->accounts->bills([
            'voynum' => $voynum,
            'paytyp' => $paytyp,
            'sort' => $sort,
        ]);
        $rows = [];
        foreach ($bills as $bill) {
            $docnum = trim((string) ($bill->docnum ?? ''));
            $activities = [];
            foreach ($this->accounts->activitiesForDocnum($docnum) as $activity) {
                $stamp = strtotime($activity['usrtim']);
                $activities[] = [
                    'date' => $this->displayDate($activity['usrdte']),
                    'time' => $stamp === false ? $activity['usrtim'] : date('H:i:s', $stamp),
                    'remarks' => $activity['remarks'],
                ];
            }
            $rows[] = [
                'docnum' => $docnum,
                'trndte' => $this->displayDate((string) ($bill->trndte ?? '')),
                'voynum' => trim((string) ($bill->voynum ?? '')),
                'activities' => $activities,
            ];
        }
        $company = $this->accounts->company();
        $first = $bills->first();

        return [
            'company' => $company['name'],
            'add1' => $company['add1'],
            'add2' => $company['add2'],
            'subtitle' => 'VOYAGE NO. : '.$voynum.'  PAYMENT MODE : '.$paytyp,
            'boldte' => trim((string) ($first->boldte ?? '')),
            'printed_at' => now('Asia/Manila')->format('m/d/Y'),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function outstandingPayload(array $filters): array
    {
        $dstcde = trim((string) ($filters['dstcde'] ?? ''));
        $paytyp = strtoupper(trim((string) ($filters['paytyp'] ?? '')));
        $incMain = $this->flag($filters['inc_main'] ?? null);
        $incCf = $this->flag($filters['inc_cf'] ?? null);
        $bills = $this->accounts->bills([
            'dstcde' => $dstcde,
            'from' => trim((string) ($filters['dtefrom'] ?? '')),
            'to' => trim((string) ($filters['dteto'] ?? '')),
            'paytyp' => $paytyp,
            'outstanding' => true,
            'sort' => 'trndte',
        ]);
        $mapped = $this->mapBills($bills, 'boldte15');
        $rows = [];
        foreach ($mapped as $row) {
            if (! $this->includeOutstanding($row['voynum'], $incMain, $incCf)) {
                continue;
            }
            if ($paytyp === 'COLLECT') {
                $row['client'] = $row['concde'];
                $row['other'] = $row['cuscde'];
            } else {
                $row['client'] = $row['cuscde'];
                $row['other'] = $row['concde'];
            }
            $rows[] = $row;
        }

        $clientHeader = $paytyp === 'COLLECT' ? 'Consignee' : 'Shipper';
        $otherHeader = $paytyp === 'COLLECT' ? 'Shipper' : 'Consignee';
        $company = $this->accounts->company();

        return [
            'company' => $company['name'],
            'add1' => $company['add1'],
            'add2' => $company['add2'],
            'subtitle' => 'DESTINATION : '.($dstcde === '' ? 'ALL' : $dstcde),
            'client_header' => $clientHeader,
            'other_header' => $otherHeader,
            'printed_at' => now('Asia/Manila')->format('m/d/Y'),
            'rows' => $rows,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $bills
     * @return list<array<string, mixed>>
     */
    private function mapBills($bills, string $dueMode): array
    {
        $docapps = $bills->map(fn ($row) => trim((string) ($row->docapp ?? '')))->all();
        $terms = $this->accounts->termDaysByCode(
            $bills->map(fn ($row) => trim((string) ($row->cretrmcde ?? '')))->all(),
        );
        $apps = $this->accounts->applicationsByDocapp($docapps);
        $rows = [];
        foreach ($bills as $bill) {
            $docapp = trim((string) ($bill->docapp ?? ''));
            $receipts = $apps[$docapp] ?? [];
            $total = (float) ($bill->trntot ?? 0);
            foreach ($receipts as $receipt) {
                $total -= $receipt['amtapp'];
            }
            $docbal = (float) ($bill->docbal ?? 0);
            $rows[] = [
                'trndte' => $this->displayDate((string) ($bill->trndte ?? '')),
                'voynum' => trim((string) ($bill->voynum ?? '')),
                'docnum' => trim((string) ($bill->docnum ?? '')),
                'cuscde' => trim((string) ($bill->cuscde ?? '')),
                'concde' => trim((string) ($bill->concde ?? '')),
                'freight' => (float) ($bill->trntot ?? 0),
                'frghtamt' => (float) ($bill->frghtamt ?? 0),
                'otrchrg' => (float) ($bill->othrchrgamt ?? 0),
                'receipts' => $receipts,
                'balance' => $total,
                'duedays' => $this->daysOverdue($bill, $terms, $dueMode, $docbal, $total),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, int>  $terms
     */
    private function daysOverdue(object $bill, array $terms, string $dueMode, float $docbal, float $remaining): string|int
    {
        $code = trim((string) ($bill->cretrmcde ?? ''));
        if (! isset($terms[$code])) {
            return 0;
        }
        $days = 0;
        try {
            if ($dueMode === 'boldte15') {
                $start = new DateTime((string) ($bill->boldte ?? ''));
                $due = (clone $start)->add(new DateInterval('P15D'))->format('Y-m-d');
            } else {
                $start = new DateTime((string) ($bill->trndte ?? ''));
                $due = (clone $start)->add(new DateInterval('P'.$terms[$code].'D'))->format('Y-m-d');
            }
            $now = date('Y-m-d');
            if ($now > $due) {
                $diff = date_diff(date_create($due), date_create($now));
                $days = $diff->format('%R%a');
            }
        } catch (\Exception) {
            $days = 0;
        }

        if ($dueMode === 'boldte15') {
            return $remaining <= 0 ? 0 : $days;
        }

        return $docbal <= 0 ? 0 : $days;
    }

    private function includeBySc(string $voynum, bool $incMain, bool $incCf): bool
    {
        if ((! $incMain && ! $incCf) || ($incMain && $incCf)) {
            return true;
        }
        if ($incMain) {
            if (! $this->isMain($voynum)) {
                return false;
            }

            return $this->likeCount($this->deleteCf($voynum)) <= 1;
        }
        if ($this->isMain($voynum)) {
            return false;
        }

        return $this->likeCount($this->deleteCf($voynum)) >= 1;
    }

    private function includeOutstanding(string $voynum, bool $incMain, bool $incCf): bool
    {
        $hasCf = str_contains($voynum, 'CF');
        if ((! $incMain && ! $incCf) || ($incMain && $incCf)) {
            return true;
        }
        if ($incMain) {
            if ($hasCf) {
                return false;
            }

            return $this->likeCount($this->deleteCf($voynum)) <= 1;
        }

        return $hasCf;
    }

    private function isMain(string $voynum): bool
    {
        $parts = explode('-', $voynum);
        $first = $parts[0] ?? '';
        $cfParts = $parts;
        $cfParts[0] = $first.'CF';
        if ($this->exists(implode('-', $cfParts))) {
            return true;
        }
        if (! str_ends_with($first, 'CF')) {
            return true;
        }
        $base = $parts;
        $base[0] = substr($first, 0, -2);

        return ! $this->exists(implode('-', $base));
    }

    private function deleteCf(string $voynum): string
    {
        $parts = explode('-', $voynum);
        $first = $parts[0] ?? '';
        if (str_ends_with($first, 'CF')) {
            $parts[0] = substr($first, 0, -2);
        }

        return str_replace(' ', '', implode('-', $parts));
    }

    private function exists(string $voynum): bool
    {
        if (! array_key_exists($voynum, $this->voyageExists)) {
            $this->voyageExists[$voynum] = $this->accounts->voyageExists($voynum);
        }

        return $this->voyageExists[$voynum];
    }

    private function likeCount(string $prefix): int
    {
        if (! array_key_exists($prefix, $this->voyageLikeCounts)) {
            $this->voyageLikeCounts[$prefix] = $this->accounts->voyageLikeCount($prefix);
        }

        return $this->voyageLikeCounts[$prefix];
    }

    private function flag(mixed $value): bool
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'on', 'true', 'yes'], true);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function payee(array $filters): string
    {
        return trim((string) ($filters['payee'] ?? 'shipper')) === 'consignee' ? 'consignee' : 'shipper';
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

    private function pdfResponse(string $binary, string $filename): Response
    {
        return new Response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function tsv(string $filename, string $text): StreamedResponse
    {
        return response()->streamDownload(function () use ($text) {
            echo $text;
        }, $filename, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }
}
