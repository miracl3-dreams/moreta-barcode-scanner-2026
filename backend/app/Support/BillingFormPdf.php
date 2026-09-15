<?php

namespace App\Support;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Http\Response;

class BillingFormPdf
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
            'Content-Disposition' => 'inline; filename="billing-form-error.pdf"',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function render(array $payload): Response
    {
        $pdf = self::make();
        $company = is_array($payload['company'] ?? null) ? $payload['company'] : [];
        $left = 25.0;
        $right = 587.0;
        $y = 767.0;

        $pdf->place($pdf->bold, 10, $left, $y, strtoupper((string) ($company['name'] ?? '')));
        $pdf->place($pdf->bold, 10, $right - 80, $y, 'BILLING FORM');
        $y -= 12;
        $pdf->place($pdf->font, 8, $right - 10, $y, 'Control / Billing No. : '.((string) ($payload['docnum'] ?? '')), 'right');

        $pdf->place($pdf->font, 8, $left, $y, 'Address: '.((string) ($company['add1'] ?? '')));
        $y -= 10;
        $pdf->place($pdf->font, 8, $left, $y, 'TIN No.: '.((string) ($company['tin'] ?? '')));
        $y -= 10;
        $tel = trim((string) ($company['telno'] ?? ''));
        $fax = trim((string) ($company['faxnum'] ?? ''));
        $pdf->place($pdf->font, 8, $left, $y, 'Tel. No. / Fax No.: '.$tel.' / '.$fax);
        $y -= 10;
        $pdf->place($pdf->font, 8, $left, $y, 'Email: '.((string) ($company['email'] ?? '')));
        $y -= 10;
        $pdf->place($pdf->font, 8, $left, $y, 'Date Printed: '.((string) ($payload['printed_at'] ?? '')));

        $y -= 22;
        $col2 = 195.0;
        $col3 = 391.0;
        $rowY = $y;
        $pdf->labeled($left, $rowY, 'Date Delivered:', (string) ($payload['del_dte'] ?? ''), $col2 - 5);
        $pdf->labeled($col2, $rowY, 'Destination:', (string) ($payload['dstdsc'] ?? ''), $col3 - 5);
        $pdf->labeled($col3, $rowY, 'Van No.:', (string) ($payload['vannum'] ?? ''), $right);
        $rowY -= 15;
        $pdf->labeled($left, $rowY, 'Shipper:', self::clip((string) ($payload['cusdsc'] ?? ''), 30), $col2 + 35);
        $pdf->labeled($col2, $rowY, 'Tel./Cel. No.:', (string) ($payload['cus_telno'] ?? ''), $col3 - 5);
        $pdf->labeled($col3, $rowY, 'Email Address:', (string) ($payload['cus_email'] ?? ''), $right);
        $rowY -= 15;
        $pdf->labeled($left, $rowY, 'Consignee:', self::clip((string) ($payload['condsc'] ?? ''), 30), $col2 + 35);
        $pdf->labeled($col2, $rowY, 'Tel./Cel. No. :', (string) ($payload['con_telno'] ?? ''), $col3 - 5);
        $pdf->labeled($col3, $rowY, 'Email Address :', (string) ($payload['con_email'] ?? ''), $right);

        $y = $rowY - 18;
        $tableTop = $y;
        $cols = [25.0, 80.0, 160.0, 300.0, 360.0, 420.0, 500.0, 587.0];
        $pdf->hline($left, $y, $right);
        $y -= 10;
        $pdf->place($pdf->font, 8, $cols[0] + 15, $y, 'QTY');
        $pdf->place($pdf->font, 8, $cols[1] + 15, $y, 'UNIT');
        $pdf->place($pdf->font, 8, $cols[2] + 20, $y, 'DESCRIPTION CARGO');
        $pdf->place($pdf->font, 8, $cols[3] + 10, $y, 'SEAL NO.');
        $pdf->place($pdf->font, 8, $cols[4] + 15, $y, 'WEIGHT');
        $pdf->place($pdf->font, 8, $cols[5] + 10, $y, 'MEASUREMENT');
        $pdf->place($pdf->font, 8, $cols[6] + 5, $y, 'DECLEARED VALUE');
        $y -= 10;
        $pdf->hline($left, $y, $right);

        $lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
        for ($index = 0; $index < 20; $index++) {
            $y -= 12;
            $line = is_array($lines[$index] ?? null) ? $lines[$index] : [];
            $qty = trim((string) ($line['qty'] ?? ''));
            $value = trim((string) ($line['value'] ?? ''));
            if ($qty !== '' && is_numeric($qty)) {
                $pdf->place($pdf->font, 8, $cols[1] - 5, $y + 3, number_format((float) $qty, 2), 'right');
            }
            $pdf->place($pdf->font, 8, $cols[1] + 5, $y + 3, (string) ($line['unit'] ?? ''));
            $pdf->place($pdf->font, 8, $cols[2] + 5, $y + 3, (string) ($line['itmdesc'] ?? ''));
            $pdf->place($pdf->font, 8, $cols[3] + 5, $y + 3, (string) ($line['sealnum'] ?? ''));
            $pdf->place($pdf->font, 8, $cols[5] - 5, $y + 3, (string) ($line['weight'] ?? ''), 'right');
            $pdf->place($pdf->font, 8, $cols[5] + 5, $y + 3, (string) ($line['measurement'] ?? ''));
            if ($value !== '' && is_numeric($value)) {
                $pdf->place($pdf->font, 8, $right - 3, $y + 3, number_format((float) $value, 2), 'right');
            }
            $pdf->hline($left, $y, $right);
        }

        $tableBottom = $y;
        foreach ($cols as $x) {
            $pdf->vline($x, $tableTop, $tableBottom);
        }

        $y -= 30;
        $pdf->place($pdf->font, 8, $right / 4, $y, 'Decleared By:');
        $pdf->place($pdf->font, 8, ($right / 4) * 3, $y, 'Checked By:');
        $y -= 23;
        $pdf->place($pdf->font, 8, $right / 6, $y, (string) ($payload['decleared_by'] ?? ''));
        $pdf->place($pdf->font, 8, ($right / 6) * 4, $y, (string) ($payload['checked_by'] ?? ''));
        $y -= 2;
        $pdf->hline($right / 8, $y, ($right / 8) * 3.5);
        $pdf->hline(($right / 8) * 5, $y, ($right / 9) * 8.5);
        $y -= 10;
        $pdf->place($pdf->font, 8, $right / 4, $y, 'SHIPPER');
        $pdf->place($pdf->font, 8, ($right / 4) * 3, $y, 'CHECKER');

        $filename = 'billing-form-'.preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($payload['docnum'] ?? 'form')).'.pdf';

        return new Response($pdf->dompdf->output(['compress' => 1]), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function labeled(float $x, float $y, string $label, string $value, float $lineTo): void
    {
        $this->place($this->font, 8, $x, $y, $label);
        $width = $this->metrics->getTextWidth($label, $this->font, 8);
        $this->place($this->font, 8, $x + $width + 8, $y, $value);
        $this->hline($x + $width + 4, $y - 2, $lineTo);
    }

    private function hline(float $x1, float $y, float $x2): void
    {
        $canvasY = self::PAGE_HEIGHT - $y;
        $this->canvas->line($x1, $canvasY, $x2, $canvasY, [0, 0, 0], 0.5);
    }

    private function vline(float $x, float $y1, float $y2): void
    {
        $this->canvas->line($x, self::PAGE_HEIGHT - $y1, $x, self::PAGE_HEIGHT - $y2, [0, 0, 0], 0.5);
    }

    private function place(string $font, float $size, float $x, float $y, string $text, string $align = 'left'): void
    {
        if ($text === '') {
            return;
        }
        if ($align === 'right') {
            $x -= $this->metrics->getTextWidth($text, $font, $size);
        }
        $baseline = $this->canvas->get_font_baseline($font, $size);
        $this->canvas->text($x, self::PAGE_HEIGHT - $y - $baseline, $text, $font, $size);
    }

    private static function clip(string $text, int $max): string
    {
        if (strlen($text) <= $max) {
            return $text;
        }

        return substr($text, 0, $max).'...';
    }
}
