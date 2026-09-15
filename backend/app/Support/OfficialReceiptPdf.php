<?php

namespace App\Support;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Http\Response;

class OfficialReceiptPdf
{
    private const PAGE_WIDTH = 612.0;

    private const PAGE_HEIGHT = 936.0;

    private const FONT_SIZE = 9.0;

    private const LEFT = 10.0;

    private const LINE_WIDTH = 590.0;

    private const BODY_TOP = 820.0;

    /** @var array<int, float> */
    private const WIDTHS = [1 => 0.0, 2 => 90.0, 3 => 160.0, 4 => 360.0, 5 => 450.0, 6 => 540.0];

    public function __construct(
        private Canvas $canvas,
        private FontMetrics $metrics,
        private string $font,
        private string $bold,
        private Dompdf $dompdf,
        private string $company = '',
        private string $from = '',
        private string $to = '',
        private string $printedAt = '',
        private bool $paged = false,
    ) {}

    public static function make(): self
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $options = new Options;
        $options->setDefaultPaperSize([0, 0, self::PAGE_WIDTH, self::PAGE_HEIGHT]);
        $options->setDefaultFont('Helvetica');
        $options->setIsFontSubsettingEnabled(false);
        $options->setIsRemoteEnabled(false);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper([0, 0, self::PAGE_WIDTH, self::PAGE_HEIGHT]);
        $fonts = $dompdf->getFontMetrics();
        $font = $fonts->getFont('Helvetica') ?: 'Helvetica';
        $bold = $fonts->getFont('Helvetica', 'bold') ?: $font;

        return new self($dompdf->getCanvas(), $fonts, $font, $bold, $dompdf);
    }

    public static function errorResponse(string $message): Response
    {
        $pdf = self::make();
        $pdf->placeText($pdf->bold, 12, 25, 890, $message);

        return new Response($pdf->dompdf->output(['compress' => 1]), 422, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="official-receipt-error.pdf"',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function render(array $payload): string
    {
        $this->company = (string) ($payload['company'] ?? '');
        $this->from = (string) ($payload['from'] ?? '');
        $this->to = (string) ($payload['to'] ?? '');
        $this->printedAt = (string) ($payload['printed_at'] ?? '');
        $this->drawHeader();
        $xtop = self::BODY_TOP;
        foreach ($payload['rows'] as $row) {
            if ($xtop <= 50) {
                $this->canvas->new_page();
                $this->drawHeader();
                $xtop = self::BODY_TOP;
            }
            $this->placeText($this->font, self::FONT_SIZE - 1, self::LEFT + 20, $xtop, $row['trndte']);
            $this->placeText($this->font, self::FONT_SIZE - 1, self::LEFT + self::WIDTHS[2], $xtop, $row['docnum']);
            $this->placeText($this->font, self::FONT_SIZE - 1, self::LEFT + self::WIDTHS[3], $xtop, $row['customer']);
            $this->placeRight($this->font, self::FONT_SIZE - 1, self::LEFT + self::WIDTHS[4], $xtop, $row['total']);
            $this->placeRight($this->font, self::FONT_SIZE - 1, self::LEFT + self::WIDTHS[5], $xtop, $row['tax']);
            $this->placeRight($this->font, self::FONT_SIZE - 1, self::LEFT + self::WIDTHS[6], $xtop, $row['net']);
            $xtop -= 12;
        }

        return $this->dompdf->output(['compress' => 1]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function exportText(array $payload): string
    {
        $tab = "\t";
        $eol = "\r\n";
        $out = $payload['company'].$eol.'Official Receipt Report'.$eol;
        $out .= 'O.R Number: '.$payload['from'].' to '.$payload['to'].$eol;
        $out .= 'Date Printed: '.$payload['printed_at'].$eol.$eol;
        $out .= implode($tab, ['Transaction Date', 'O.R. No.', 'Customer', 'Total Amount', 'Tax Amount', 'Net Amount']).$eol;
        foreach ($payload['rows'] as $row) {
            $out .= $row['trndte'].$tab.$row['docnum'].$tab.$row['customer_full'].$tab.$row['total'].$tab.$row['tax'].$tab.$row['net'].$eol;
        }

        return $out;
    }

    private function drawHeader(): void
    {
        $xtop = 890.0;
        $this->placeText($this->font, self::FONT_SIZE + 2, self::LEFT, $xtop, $this->company);
        $xtop -= 12;
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop, 'Official Receipt Report');
        $xtop -= 12;
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop, 'O.R Number: '.$this->from.' to '.$this->to);
        $xtop -= 12;
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop, 'Date Printed: '.$this->printedAt);
        $xtop -= 20;
        $headers = [
            1 => ['Transaction Date', 'left'],
            2 => ['O.R. No.', 'left'],
            3 => ['Customer', 'left'],
            4 => ['Total Amount', 'right'],
            5 => ['Tax Amount', 'right'],
            6 => ['Net Amount', 'right'],
        ];
        foreach ($headers as $i => $header) {
            $x = self::LEFT + self::WIDTHS[$i];
            if ($header[1] === 'right') {
                $this->placeRight($this->bold, self::FONT_SIZE - 1, $x, $xtop, $header[0]);
            } else {
                $this->placeText($this->bold, self::FONT_SIZE - 1, $x, $xtop, $header[0]);
            }
        }
        $xtop -= 2;
        $this->canvas->line(self::LEFT, self::PAGE_HEIGHT - $xtop, self::LINE_WIDTH, self::PAGE_HEIGHT - $xtop, [0, 0, 0], 0.5);
        if (! $this->paged) {
            $this->paged = true;
            $baseline = $this->canvas->get_font_baseline($this->font, 8);
            $this->canvas->page_text(self::LINE_WIDTH - 10, self::PAGE_HEIGHT - 8 - $baseline, 'Page {PAGE_NUM} of {PAGE_COUNT}', $this->font, 8);
        }
    }

    private function placeRight(string $font, float $size, float $rightX, float $ezY, string $text): void
    {
        $text = self::pdfText($text);
        if ($text === '') {
            return;
        }
        $width = $this->metrics->getTextWidth($text, $font, $size);
        $this->placeText($font, $size, $rightX - $width, $ezY, $text);
    }

    private function placeText(string $font, float $size, float $x, float $ezY, string $text): void
    {
        $text = self::pdfText($text);
        if ($text === '') {
            return;
        }
        $canvasY = self::PAGE_HEIGHT - $ezY - $this->canvas->get_font_baseline($font, $size);
        $this->canvas->text($x, $canvasY, $text, $font, $size);
    }

    private static function pdfText(string $text): string
    {
        return str_replace(["\r", "\n"], ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
