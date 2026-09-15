<?php

namespace App\Services;

use App\Repositories\OfficialReceiptRepository;
use App\Support\OfficialReceiptPdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfficialReceiptService
{
    public function __construct(private OfficialReceiptRepository $receipts) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function pdf(array $filters): Response
    {
        $from = trim((string) ($filters['txt_ornumberfrom'] ?? ''));
        $to = trim((string) ($filters['txt_ornumberto'] ?? ''));
        if ($from !== '' && $to !== '' && $from > $to) {
            return OfficialReceiptPdf::errorResponse('O.R Number From cannot be greater than O.R Number To');
        }

        return new Response(
            OfficialReceiptPdf::make()->render($this->payload($from, $to, true)),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="official-receipt.pdf"',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function export(array $filters): StreamedResponse|Response
    {
        $from = trim((string) ($filters['txt_ornumberfrom'] ?? ''));
        $to = trim((string) ($filters['txt_ornumberto'] ?? ''));
        if ($from !== '' && $to !== '' && $from > $to) {
            return response('O.R Number From cannot be greater than O.R Number To', 422);
        }
        $text = OfficialReceiptPdf::exportText($this->payload($from, $to, false));

        return response()->streamDownload(function () use ($text) {
            echo $text;
        }, 'pdf_official_receipt.xls', [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $from, string $to, bool $truncate): array
    {
        $receipts = $this->receipts->receipts($from, $to);
        $apps = $this->receipts->applicationsByDocnum(
            $receipts->map(fn ($row) => trim((string) ($row->docnum ?? '')))->all(),
        );
        $rows = [];
        foreach ($receipts as $receipt) {
            $docnum = trim((string) ($receipt->docnum ?? ''));
            $total = 0.0;
            $tax = 0.0;
            foreach ($apps[$docnum] ?? [] as $app) {
                $total += $app['amtapp'];
                $tax += $app['evatamt'] + $app['ewtamt'];
            }
            $customer = strtolower(trim((string) ($receipt->payee ?? ''))) === 'consignee'
                ? trim((string) ($receipt->condsc ?? ''))
                : trim((string) ($receipt->cusdsc ?? ''));
            $display = $customer;
            if ($truncate && strlen($display) > 25) {
                $display = substr($display, 0, 25).'...';
            }
            $rows[] = [
                'trndte' => $this->mdy((string) ($receipt->trndte ?? '')),
                'docnum' => $docnum,
                'customer' => $display,
                'customer_full' => $customer,
                'total' => number_format($total, 2),
                'tax' => number_format($tax, 2),
                'net' => number_format($total - $tax, 2),
            ];
        }
        $company = $this->receipts->company();

        return [
            'company' => $company['name'],
            'from' => $from,
            'to' => $to,
            'printed_at' => now('Asia/Manila')->format('F j, Y h:i:s A'),
            'rows' => $rows,
        ];
    }

    private function mdy(string $date): string
    {
        $date = trim($date);
        if ($date === '' || str_starts_with($date, '0000-00-00')) {
            return '';
        }
        $stamp = strtotime($date);

        return $stamp === false ? '' : date('m-d-Y', $stamp);
    }
}
