<?php

namespace App\Services;

use App\Repositories\ManifestCargoRepository;
use App\Support\ClearingManifestPdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClearingManifestService
{
    public function __construct(
        private ManifestCargoRepository $manifest,
        private ManifestCargoService $cargo,
    ) {}

    public function pdf(string $voynum): Response
    {
        return new Response(
            ClearingManifestPdf::make()->render($this->payload($voynum), false),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="clearing-manifest.pdf"',
            ],
        );
    }

    public function export(string $voynum): StreamedResponse
    {
        $text = ClearingManifestPdf::exportText($this->payload($voynum));

        return response()->streamDownload(function () use ($text) {
            echo $text;
        }, 'pdf_clearing_manifest.xls', [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $voynum): array
    {
        $voynum = trim($voynum);
        $voyage = $this->manifest->voyage($voynum) ?? [
            'voynum' => $voynum,
            'vsslcde' => '',
            'trndte' => '',
            'etadte' => '',
            'origin' => '',
            'dstcde' => '',
        ];
        $bills = $this->manifest->bills($voynum, [], false);
        $shippers = $this->manifest->shipperNames(
            $bills->map(fn ($row) => trim((string) ($row->cuscde ?? '')))->all(),
        );
        $consignees = $this->manifest->consigneeNames(
            $bills->map(fn ($row) => trim((string) ($row->concde ?? '')))->all(),
        );

        $rows = [];
        foreach ($bills as $bill) {
            $cuscde = trim((string) ($bill->cuscde ?? ''));
            $concde = trim((string) ($bill->concde ?? ''));
            $lines = [];
            foreach ($this->manifest->cargoLines(trim((string) ($bill->docnum ?? '')), 'linenum') as $line) {
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
                'lines' => $lines,
            ];
        }

        $company = $this->manifest->company();

        return [
            'company' => $company['name'],
            'voyage' => [
                'voynum' => $voyage['voynum'] !== '' ? $voyage['voynum'] : $voynum,
                'vsslcde' => $voyage['vsslcde'],
                'sailing' => $this->cargo->displayDate($voyage['trndte']),
                'eta' => $this->cargo->displayDate($voyage['etadte']),
                'origin' => $voyage['origin'],
                'dstcde' => $voyage['dstcde'],
            ],
            'printed_at' => now('Asia/Manila')->format('F j, Y h:i:s A'),
            'bills' => $rows,
        ];
    }
}
