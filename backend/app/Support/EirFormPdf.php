<?php

namespace App\Support;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Http\Response;

class EirFormPdf
{
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
            'Content-Disposition' => 'inline; filename="eir-error.pdf"',
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
        $y = 770.0;

        $pdf->place($pdf->bold, 10, $right - 10, $y, 'EIR FORM', 'right');
        $y -= 12;
        $pdf->place($pdf->font, 8, $right - 10, $y, 'Control / EIR No.: '.((string) ($payload['docnum'] ?? '')), 'right');
        $pdf->place($pdf->bold, 10, $left, $y + 12, (string) ($company['name'] ?? ''));
        $pdf->place($pdf->font, 8, $left, $y, 'TIN No.: '.((string) ($company['tin'] ?? '')));
        $y -= 10;
        $pdf->place($pdf->font, 8, $left, $y, 'Tel. No. / Fax No.: '.((string) ($company['telno'] ?? '')).' / '.((string) ($company['faxnum'] ?? '')));
        $y -= 10;
        $pdf->place($pdf->font, 8, $left, $y, 'Email: '.((string) ($company['email'] ?? '')));
        $y -= 10;
        $pdf->place($pdf->font, 8, $left, $y, 'Date Printed: '.((string) ($payload['printed_at'] ?? '')));

        $y -= 18;
        $mid = 310.0;
        $leftRows = [
            'Control / Billing No.:' => (string) ($payload['billing_no'] ?? ''),
            'Van No.:' => (string) ($payload['vannum'] ?? ''),
            'Origin / Destination:' => (string) ($payload['origin'] ?? ''),
            'Type:' => (string) ($payload['type'] ?? ''),
            'Weight:' => (string) ($payload['weight'] ?? ''),
            'Vessel / Voyage No.:' => (string) ($payload['voynum'] ?? ''),
            'Shipper:' => (string) ($payload['cusdsc'] ?? ''),
            'Consignee:' => (string) ($payload['condsc'] ?? ''),
            'Trucker:' => (string) ($payload['trucker'] ?? ''),
            'Driver Name:' => (string) ($payload['driver_name'] ?? ''),
            'Type of Move:' => (string) ($payload['move'] ?? ''),
            'Return Van To:' => (string) ($payload['dstdsc'] ?? ''),
            'Special Instruction:' => (string) ($payload['special_ins'] ?? ''),
        ];
        $rightRows = [
            'Issue Date/Time:' => trim((string) ($payload['issue_dte'] ?? '').' '.(string) ($payload['issue_time'] ?? '')),
            'Acceptance Date:' => (string) ($payload['accpt_dte'] ?? ''),
            'Size:' => (string) ($payload['size'] ?? ''),
            'Seal No.:' => (string) ($payload['seal_no'] ?? ''),
            'Plate No.:' => (string) ($payload['plateno'] ?? ''),
            'Date/Time of Move:' => trim((string) ($payload['move_dte'] ?? '').' '.(string) ($payload['move_time'] ?? '')),
        ];
        $rowY = $y;
        foreach ($leftRows as $label => $value) {
            $pdf->place($pdf->font, 8, $left, $rowY, $label);
            $pdf->place($pdf->font, 8, $left + 120, $rowY, $value);
            $rowY -= 12;
        }
        $rightY = $y;
        foreach ($rightRows as $label => $value) {
            $pdf->place($pdf->font, 8, $mid, $rightY, $label);
            $pdf->place($pdf->font, 8, $mid + 120, $rightY, $value);
            $rightY -= 12;
        }

        $y = min($rowY, $rightY) - 8;
        $pdf->place($pdf->bold, 8, $left, $y, 'REMARKS:');
        $y -= 12;
        $remarks = is_array($payload['remarks'] ?? null) ? $payload['remarks'] : [];
        if ($remarks === []) {
            $pdf->place($pdf->font, 8, $left + 10, $y, '** NO AVAILABLE REMARKS FOUND **');
            $y -= 12;
        } else {
            foreach ($remarks as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $pdf->place($pdf->font, 8, $left + 10, $y, ((string) ($row['desc'] ?? '')).' : '.((string) ($row['codes'] ?? '')));
                $y -= 12;
            }
        }

        $y -= 16;
        $pdf->place($pdf->font, 8, $left + 40, $y, "SHIPPER'S REPRESENTATIVE / DRIVER:");
        $pdf->place($pdf->font, 8, $mid + 40, $y, 'SHIPPING CHECKER:');
        $y -= 36;
        $pdf->place($pdf->font, 8, $left + 40, $y, (string) ($payload['van_received_by'] ?? ''));
        $pdf->place($pdf->font, 8, $mid + 40, $y, (string) ($payload['van_released_by'] ?? ''));

        $y -= 24;
        $pdf->place($pdf->bold, 8, $left, $y, 'DAMAGE CODE');
        $y -= 12;
        $defs = [
            'LK' => 'Leaking',
            'BE' => 'Bent',
            'BR' => 'Broken',
            'CR' => 'Cracked',
            'DI' => 'Distored',
            'H' => 'Hole',
            'L' => 'Loose',
            'M' => 'Missing',
            'T' => 'Torn/Rip',
            'PI' => 'Punshed In / Dent',
            'C' => 'Cut',
            'PO' => 'Pushed Out / Dent',
            'BO' => 'Burn Out',
        ];
        $codes = array_keys($defs);
        $half = (int) ceil(count($codes) / 2);
        $start = $y;
        foreach ($codes as $index => $code) {
            $col = $index < $half ? 0 : 1;
            $row = $index < $half ? $index : $index - $half;
            $pdf->place($pdf->font, 8, $left + ($col * 160), $start - ($row * 11), $code.'  '.$defs[$code]);
        }

        $filename = 'eir-'.preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($payload['docnum'] ?? 'form')).'.pdf';

        return new Response($pdf->dompdf->output(['compress' => 1]), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
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
}
