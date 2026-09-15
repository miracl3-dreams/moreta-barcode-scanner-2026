<?php

namespace App\Support;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Http\Response;

class LoadUnloadSheetPdf
{
    private const PAGE_WIDTH = 792.0;

    private const PAGE_HEIGHT = 612.0;

    private const LINE_WIDTH = 790.0;

    private const BODY_TOP = 495.0;

    /** @var list<int> */
    private const HEADER_POS = [10, 30, 80, 120, 153, 248, 320, 370, 490, 540, 655, 760];

    public function __construct(
        private Canvas $canvas,
        private FontMetrics $metrics,
        private string $font,
        private string $bold,
        private Dompdf $dompdf,
        private array $payload = [],
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
        $pdf->placeText($pdf->bold, 12, 25, 580, $message);

        return new Response($pdf->dompdf->output(['compress' => 1]), 422, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="load-unload-sheet-error.pdf"',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function render(array $payload): string
    {
        $this->payload = $payload;
        $this->drawHeader();
        $xtop = self::BODY_TOP;
        $number = 1;
        foreach ($payload['rows'] as $row) {
            $this->placeText($this->font, 6.0, 5, $xtop, (string) $number);
            $this->placeText($this->font, 8.0, 20, $xtop, (string) $row['bolnum']);
            $this->placeText($this->font, 7.0, 135, $xtop, (string) $row['marks']);
            $this->placeText($this->font, 7.0, 155, $xtop, (string) $row['qty']);
            $this->placeText($this->font, 7.0, 186, $xtop, (string) $row['catcde']);
            $this->placeText($this->font, 7.0, 343, $xtop, (string) $row['vannum']);
            $this->placeText($this->font, 7.0, 395, $xtop, (string) $row['sealnum']);
            $this->placeText($this->font, 7.0, 420, $xtop, (string) $row['description']);
            $this->placeText($this->font, 8.0, 600, $xtop, (string) $row['mos']);
            $this->placeText($this->font, 8.0, 590, $xtop, (string) $row['consignee']);
            $this->placeText($this->font, 8.0, 755, $xtop, (string) $row['shipper']);
            $this->placeRight($this->font, 8.0, 885, $xtop, (string) $row['mea']);
            $xtop -= 12;
            if ($xtop <= 30) {
                $this->canvas->new_page();
                $this->drawHeader();
                $xtop = self::BODY_TOP;
            }
            $number++;
        }

        $baseline = $this->canvas->get_font_baseline($this->font, 8);
        $this->canvas->page_text(
            self::LINE_WIDTH - 10,
            self::PAGE_HEIGHT - 8 - $baseline,
            'Page {PAGE_NUM} of {PAGE_COUNT}',
            $this->font,
            8,
        );

        return $this->dompdf->output(['compress' => 1]);
    }

    private function drawHeader(): void
    {
        $xtop = 590.0;
        $this->placeText($this->bold, 11.0, 10, $xtop, (string) $this->payload['company']);
        $xtop -= 12;
        $this->placeText($this->font, 9.0, 10, $xtop, 'Loading And Unloading Tally Sheet ');
        $xtop -= 12;
        $this->placeText($this->font, 9.0, 10, $xtop, 'Voyage # : '.$this->payload['voynum']);
        $xtop -= 12;
        $this->placeText($this->font, 9.0, 10, $xtop, 'Sailing Date : '.$this->payload['sailing_date']);
        $xtop -= 12;
        $this->placeText($this->font, 9.0, 10, $xtop, 'From : '.$this->payload['origin'].' To '.$this->payload['destination']);
        $xtop -= 30;
        $this->canvas->filled_rectangle(10, self::PAGE_HEIGHT - $xtop - 14, self::LINE_WIDTH - 10, 14, [0.8, 0.8, 0.8]);
        $this->canvas->line(10, self::PAGE_HEIGHT - $xtop, self::LINE_WIDTH, self::PAGE_HEIGHT - $xtop, [0, 0, 0], 0.5);
        $xtop += 5;
        $headers = ['No.', 'B/L No.', 'Marks', 'Qty', 'Cargo Cat.', 'Van #', 'Seal #', 'Description', 'MOS', 'Consignee', 'Shipper', 'Mea'];
        foreach ($headers as $index => $header) {
            $this->placeText($this->bold, 9.0, (float) self::HEADER_POS[$index], $xtop, $header);
        }
        $this->placeText($this->font, 8.0, 20, 8, 'Date Printed : '.$this->payload['printed_at']);
    }

    private function placeRight(string $font, float $size, float $right, float $ezY, string $text): void
    {
        $text = self::pdfText($text);
        if ($text === '') {
            return;
        }
        $width = $this->metrics->getTextWidth($text, $font, $size);
        $this->placeText($font, $size, $right - $width, $ezY, $text);
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
        $text = str_replace(["\0", "\r", "\n", "\t"], ['', ' ', ' ', ' '], $text);
        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'Windows-1252//IGNORE', $text);
            if ($converted !== false) {
                return $converted;
            }
        }

        return $text;
    }
}
