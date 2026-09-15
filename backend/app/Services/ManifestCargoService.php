<?php

namespace App\Services;

use App\Repositories\ManifestCargoRepository;
use App\Support\ManifestCargoPdf;
use Illuminate\Http\Response;

class ManifestCargoService
{
    public function __construct(private ManifestCargoRepository $manifest) {}

    public function officePdf(string $voynum): Response
    {
        return $this->pdf($voynum, 'office');
    }

    public function checkerPdf(string $voynum): Response
    {
        return $this->pdf($voynum, 'checker');
    }

    private function pdf(string $voynum, string $copy): Response
    {
        $binary = $copy === 'checker'
            ? ManifestCargoPdf::checker()->render($this->payload($voynum, true))
            : ManifestCargoPdf::office()->render($this->payload($voynum, true));

        return new Response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="manifest-of-cargo-'.$copy.'.pdf"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(string $voynum, bool $excludeCancelled): array
    {
        $voynum = trim($voynum);
        $groups = $this->manifest->chargeGroups();
        $fieldsByGroup = $this->manifest->chargeFieldsByGroup($groups);
        $extra = [];
        foreach ($fieldsByGroup as $fields) {
            foreach ($fields as $field) {
                $extra[] = $field;
            }
        }

        $voyage = $this->manifest->voyage($voynum) ?? [
            'voynum' => $voynum,
            'vsslcde' => '',
            'trndte' => '',
            'etadte' => '',
            'origin' => '',
            'dstcde' => '',
        ];
        $bills = $this->manifest->bills($voynum, $extra, $excludeCancelled);
        $shippers = $this->manifest->shipperNames(
            $bills->map(fn ($row) => trim((string) ($row->cuscde ?? '')))->all(),
        );
        $consignees = $this->manifest->consigneeNames(
            $bills->map(fn ($row) => trim((string) ($row->concde ?? '')))->all(),
        );
        $receipts = $this->manifest->receiptsByDocapp(
            $bills->map(fn ($row) => trim((string) ($row->docapp ?? '')))->all(),
        );

        $rows = [];
        foreach ($bills as $bill) {
            $cuscde = trim((string) ($bill->cuscde ?? ''));
            $concde = trim((string) ($bill->concde ?? ''));
            $docapp = trim((string) ($bill->docapp ?? ''));
            $receipt = $receipts[$docapp] ?? ['ornum' => '', 'ordate' => ''];
            $charges = [];
            foreach ($groups as $group) {
                $sum = 0.0;
                foreach ($fieldsByGroup[$group] ?? [] as $field) {
                    $sum += (float) ($bill->{$field} ?? 0);
                }
                $charges[$group] = $sum;
            }
            $lines = [];
            foreach ($this->manifest->cargoLines(trim((string) ($bill->docnum ?? ''))) as $line) {
                $soc = strtoupper(trim((string) ($line->soc ?? ''))) === 'Y';
                $lines[] = [
                    'marks' => trim((string) ($line->itmcde ?? '')),
                    'class' => trim((string) ($line->class ?? '')),
                    'qty' => $line->qty === null ? '' : trim((string) $line->qty),
                    'catcde' => trim((string) ($line->catcde ?? '')),
                    'vannum' => $soc ? trim((string) ($line->socvannum ?? '')) : trim((string) ($line->vannum ?? '')),
                    'sealnum' => trim((string) ($line->sealnum ?? '')),
                    'classdsc' => trim((string) ($line->classdsc ?? '')),
                    'itmqty' => (float) ($line->itmqty ?? 0),
                    'weiamt' => (float) ($line->weiamt ?? 0),
                    'value' => (float) ($line->value ?? 0),
                ];
            }
            $rows[] = [
                'docnum' => trim((string) ($bill->docnum ?? '')),
                'cuscde' => $cuscde,
                'cusdsc' => $shippers[$cuscde] ?? '',
                'concde' => $concde,
                'condsc' => $consignees[$concde] ?? '',
                'trntot' => (float) ($bill->trntot ?? 0),
                'shipmode' => trim((string) ($bill->shipmode ?? '')),
                'ornum' => $receipt['ornum'],
                'ordate' => $this->displayDate($receipt['ordate']),
                'charges' => $charges,
                'lines' => $lines,
            ];
        }

        return [
            'company' => $this->manifest->company(),
            'voyage' => [
                'voynum' => $voyage['voynum'] !== '' ? $voyage['voynum'] : $voynum,
                'vsslcde' => $voyage['vsslcde'],
                'sailing' => $this->displayDate($voyage['trndte']),
                'eta' => $this->displayDate($voyage['etadte']),
                'origin' => $voyage['origin'],
                'dstcde' => $voyage['dstcde'],
            ],
            'printed_at' => now('Asia/Manila')->format('F j, Y h:i:s A'),
            'groups' => $groups,
            'bills' => $rows,
        ];
    }

    public function displayDate(string $date): string
    {
        $date = trim($date);
        if ($date === '' || str_starts_with($date, '0000-00-00')) {
            return '';
        }
        $stamp = strtotime($date);

        return $stamp === false ? '' : date('n-j-Y', $stamp);
    }
}
