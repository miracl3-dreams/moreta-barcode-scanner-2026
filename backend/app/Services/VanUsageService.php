<?php

namespace App\Services;

use App\Repositories\VanUsageRepository;
use App\Support\Text;
use App\Support\VanUsagePdf;
use Illuminate\Http\Response;

class VanUsageService
{
    public function __construct(private VanUsageRepository $usage) {}

    /**
     * @return array{destinations: list<array{dstcde: string, dstdsc: string}>}
     */
    public function lookups(): array
    {
        return [
            'destinations' => $this->usage->destinations(),
        ];
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public function searchVans(string $term): array
    {
        return $this->usage->searchVans($term);
    }

    public function summaryPdf(string $from, string $to, bool $includeSoc): Response
    {
        $from = $this->sqlDate($from);
        $to = $this->sqlDate($to);

        return $this->pdfResponse(
            VanUsagePdf::make()->summary(
                $this->usage->companyName(),
                $from,
                $to,
                $this->printedAt(),
                $this->summaryRows($from, $to, $includeSoc),
            ),
            'van-usage.pdf',
        );
    }

    public function byLocationPdf(string $origin): Response
    {
        $origin = trim($origin);
        $label = $origin === '' ? 'ALL' : $origin;

        return $this->pdfResponse(
            VanUsagePdf::make()->byLocation(
                $this->usage->companyName(),
                $label,
                $this->printedAt(),
                $this->usage->locationRows($origin),
            ),
            'van-usage-by-location.pdf',
        );
    }

    public function byVanNumberPdf(string $vannum, string $from, string $to, bool $showLocation): Response
    {
        $van = $this->usage->findVan($vannum);
        $resolved = $van['prevannum'] ?? '';
        $location = trim((string) ($van['lastloc'] ?? ''));
        if ($location === '') {
            $location = 'Not Assigned';
        }

        $sqlFrom = $this->sqlDate($from);
        $sqlTo = $this->sqlDate($to);
        $periodFrom = $this->periodLabel($from, $sqlFrom);
        $periodTo = $this->periodLabel($to, $sqlTo);

        return $this->pdfResponse(
            VanUsagePdf::make()->byVanNumber(
                $this->usage->companyName(),
                $periodFrom,
                $periodTo,
                $resolved,
                $location,
                $this->printedAt(),
                $this->vanNumberRows($resolved, $sqlFrom, $sqlTo),
                $showLocation,
            ),
            $showLocation ? 'van-usage-by-van.pdf' : 'van-usage-history.pdf',
        );
    }

    /**
     * @return \Generator<int, array{0: int, 1: list<string>}>
     */
    private function summaryRows(string $from, string $to, bool $includeSoc): \Generator
    {
        $line = 1;
        foreach ($this->usage->summaryCursor($from, $to, $includeSoc) as $record) {
            $vannum = strtoupper(trim((string) ($record->soc ?? ''))) === 'Y'
                ? trim((string) ($record->socvannum ?? ''))
                : trim((string) ($record->vannum ?? ''));
            if ($vannum === '') {
                continue;
            }

            $mapped = $this->mapUsage($record, false);
            yield [$line, [
                ' '.Text::clip($vannum, 11),
                $this->displayDate($record->trndte ?? ''),
                Text::clip($mapped['origin'], 15),
                Text::clip($mapped['dstcde'], 15),
                $mapped['voynum'],
                Text::clip($mapped['docnum'], 20),
            ]];
            $line++;
        }
    }

    /**
     * @return \Generator<int, array{0: int, 1: list<string>}>
     */
    private function vanNumberRows(string $vannum, string $from, string $to): \Generator
    {
        if ($vannum === '') {
            return;
        }

        $line = 1;
        foreach ($this->usage->byVanNumberCursor($vannum, $from, $to) as $record) {
            $mapped = $this->mapUsage($record, true);
            yield [$line, [
                $this->displayDate($record->trndte ?? ''),
                $mapped['voynum'],
                $mapped['docnum'],
                Text::clip($mapped['shipper'], 25),
                Text::clip($mapped['origin'], 13),
                Text::clip($mapped['dstcde'], 15),
            ]];
            $line++;
        }
    }

    /**
     * @return array{voynum: string, origin: string, dstcde: string, docnum: string, shipper: string}
     */
    private function mapUsage(object $record, bool $fromVoyage): array
    {
        $trncde = strtoupper(trim((string) ($record->trncde ?? '')));
        $voynum = trim((string) ($record->voynum ?? ''));
        $origin = $fromVoyage
            ? trim((string) ($record->voy_origin ?? ''))
            : trim((string) ($record->origin ?? ''));
        $dstcde = $fromVoyage
            ? trim((string) ($record->voy_dstcde ?? ''))
            : trim((string) ($record->dstcde ?? ''));
        $docnum = $fromVoyage ? '' : trim((string) ($record->docnum ?? ''));
        $shipper = trim((string) ($record->cuscde ?? ''));

        if ($trncde === 'BL' && $fromVoyage) {
            $docnum = trim((string) ($record->docnum ?? ''));
        } elseif ($trncde === 'EV') {
            $docnum = 'EMPTY VAN';
        } elseif ($trncde === 'VT') {
            $voynum = trim((string) ($record->vt_status ?? ''));
            $origin = trim((string) ($record->origin ?? ''));
            $dstcde = trim((string) ($record->dstcde ?? ''));
            $who = strtolower(trim((string) ($record->payee ?? ''))) === 'shipper'
                ? trim((string) ($record->cuscde ?? ''))
                : trim((string) ($record->concde ?? ''));
            $docnum = 'BY : '.$who;
        }

        return [
            'voynum' => $voynum,
            'origin' => $origin,
            'dstcde' => $dstcde,
            'docnum' => $docnum,
            'shipper' => $shipper,
        ];
    }

    private function pdfResponse(string $content, string $filename): Response
    {
        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function printedAt(): string
    {
        return now('Asia/Manila')->format('F j, Y');
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
        if ($stamp === false) {
            return '';
        }

        return date('Y-m-d', $stamp);
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
        if ($stamp === false) {
            return $sql;
        }

        return date('m-d-Y', $stamp);
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
