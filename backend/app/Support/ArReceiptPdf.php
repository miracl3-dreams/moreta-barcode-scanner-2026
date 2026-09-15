<?php

namespace App\Support;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Http\Response;

class ArReceiptPdf
{
    private const PAGE_WIDTH = 612.0;

    private const PAGE_HEIGHT = 792.0;

    public function __construct(
        private Canvas $canvas,
        private FontMetrics $metrics,
        private string $font,
        private string $bold,
        private Dompdf $dompdf,
    ) {}

    public static function make(): self
    {
        $options = new Options;
        $options->setDefaultPaperSize('letter');
        $options->setDefaultPaperOrientation('portrait');
        $options->setDefaultFont('Helvetica');
        $options->setIsFontSubsettingEnabled(false);
        $options->setIsRemoteEnabled(false);

        $dompdf = new Dompdf($options);
        $fonts = $dompdf->getFontMetrics();
        $font = $fonts->getFont('Helvetica') ?: 'Helvetica';
        $bold = $fonts->getFont('Helvetica', 'bold') ?: $font;

        return new self($dompdf->getCanvas(), $fonts, $font, $bold, $dompdf);
    }

    public static function errorResponse(string $message): Response
    {
        $pdf = self::make();
        $pdf->place($pdf->bold, 12, 36, 750, $message);

        return new Response($pdf->dompdf->output(['compress' => 1]), 422, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="official-receipt-error.pdf"',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function render(array $payload): Response
    {
        $pdf = self::make();
        $htop = (float) ($payload['htop'] ?? 0);
        $hleft = (float) ($payload['hleft'] ?? 0);
        $dtop = (float) ($payload['dtop'] ?? 0);
        $dleft = (float) ($payload['dleft'] ?? 0);

        $y = 750 - $htop;
        $pdf->place($pdf->bold, 12, 36 + $hleft, $y, (string) ($payload['company'] ?? ''));
        $y -= 18;
        $pdf->place($pdf->bold, 11, 36 + $hleft, $y, 'OFFICIAL RECEIPT');
        $y -= 22;
        $pdf->place($pdf->font, 10, 36 + $hleft, $y, 'OR # '.(string) ($payload['docnum'] ?? ''));
        $pdf->place($pdf->font, 10, 360 + $hleft, $y, (string) ($payload['date'] ?? ''));
        $y -= 18;
        $pdf->place($pdf->font, 10, 36 + $hleft, $y, 'Received from: '.(string) ($payload['payee'] ?? ''));
        if (trim((string) ($payload['address'] ?? '')) !== '') {
            $y -= 14;
            $pdf->place($pdf->font, 9, 36 + $hleft, $y, (string) $payload['address']);
        }
        $y -= 16;
        $pdf->place($pdf->font, 10, 36 + $hleft, $y, 'Amount: '.(string) ($payload['amount'] ?? '0.00'));
        $y -= 14;
        $pdf->place($pdf->font, 9, 36 + $hleft, $y, (string) ($payload['amount_words'] ?? ''));

        $y = min($y, 620) - $dtop;
        $pdf->place($pdf->bold, 10, 36 + $dleft, $y, 'B/L No.');
        $pdf->place($pdf->bold, 10, 180 + $dleft, $y, 'Voyage');
        $pdf->place($pdf->bold, 10, 320 + $dleft, $y, 'Amount Applied');
        $y -= 14;

        /** @var list<array{docnum: string, voynum: string, amtappfor: float}> $lines */
        $lines = $payload['lines'] ?? [];
        if ($lines === []) {
            $pdf->place($pdf->font, 9, 36 + $dleft, $y, 'No applied bills.');
            $y -= 14;
        }
        foreach ($lines as $line) {
            if ($y < 80) {
                $pdf->canvas->new_page();
                $y = 750;
            }
            $pdf->place($pdf->font, 9, 36 + $dleft, $y, (string) ($line['docnum'] ?? ''));
            $pdf->place($pdf->font, 9, 180 + $dleft, $y, (string) ($line['voynum'] ?? ''));
            $pdf->place($pdf->font, 9, 320 + $dleft, $y, number_format((float) ($line['amtappfor'] ?? 0), 2));
            $y -= 13;
        }

        $y -= 10;
        $pdf->place($pdf->font, 9, 36 + $dleft, $y, 'VATable: '.(string) ($payload['vatable'] ?? '0.00'));
        $y -= 13;
        $pdf->place($pdf->font, 9, 36 + $dleft, $y, 'VAT: '.(string) ($payload['vat'] ?? '0.00'));
        $y -= 13;
        $pdf->place($pdf->font, 9, 36 + $dleft, $y, 'Less EWT/EVAT: '.(string) ($payload['witvat'] ?? '0.00'));
        $y -= 16;
        if (trim((string) ($payload['banks'] ?? '')) !== '') {
            $pdf->place($pdf->font, 9, 36 + $dleft, $y, (string) $payload['banks']);
            $y -= 13;
        }
        if (trim((string) ($payload['check_dates'] ?? '')) !== '') {
            $pdf->place($pdf->font, 9, 36 + $dleft, $y, 'Date: '.(string) $payload['check_dates']);
            $y -= 13;
        }
        if (trim((string) ($payload['checks'] ?? '')) !== '') {
            $pdf->place($pdf->font, 9, 36 + $dleft, $y, 'Check/Ref: '.(string) $payload['checks']);
        }

        $filename = 'or-'.preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($payload['docnum'] ?? 'receipt')).'.pdf';

        return new Response($pdf->dompdf->output(['compress' => 1]), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function place(string $font, float $size, float $x, float $y, string $text): void
    {
        if ($text === '') {
            return;
        }
        $baseline = $this->canvas->get_font_baseline($font, $size);
        $this->canvas->text($x, self::PAGE_HEIGHT - $y - $baseline, $text, $font, $size);
    }
}
