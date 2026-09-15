<?php

namespace App\Support;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Http\Response;

class PostLoadingPdf
{
    public function __construct(
        private Canvas $canvas,
        private FontMetrics $metrics,
        private string $font,
        private string $bold,
        private Dompdf $dompdf,
        private float $pageHeight = 612.0,
        private float $fontSize = 8.0,
        private float $lineWidth = 890.0,
        private string $company = '',
        private string $title = '',
        private string $voynum = '',
        private string $catcde = '',
        private string $sailingDate = '',
        private string $vessel = '',
        private string $printedAt = '',
        /** @var list<string> */
        private array $headers = [],
        /** @var list<int> */
        private array $positions = [],
    ) {}

    public static function make(): self
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $options = new Options;
        $options->setDefaultPaperSize([0, 0, 936.0, 612.0]);
        $options->setDefaultFont('Helvetica');
        $options->setIsFontSubsettingEnabled(false);
        $options->setIsRemoteEnabled(false);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper([0, 0, 936.0, 612.0]);
        $fonts = $dompdf->getFontMetrics();
        $font = $fonts->getFont('Helvetica') ?: 'Helvetica';
        $bold = $fonts->getFont('Helvetica', 'bold') ?: $font;

        return new self($dompdf->getCanvas(), $fonts, $font, $bold, $dompdf);
    }

    public static function errorResponse(string $message): Response
    {
        $pdf = self::make();
        $pdf->placeText($pdf->bold, 12, 25, 560, $message);

        return new Response($pdf->dompdf->output(['compress' => 1]), 422, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="post-loading-error.pdf"',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function render(array $payload): string
    {
        $this->company = (string) $payload['company'];
        $this->title = (string) $payload['title'];
        $this->voynum = (string) $payload['voynum'];
        $this->catcde = (string) $payload['catcde'];
        $this->sailingDate = (string) $payload['sailing_date'];
        $this->vessel = (string) $payload['vessel'];
        $this->printedAt = (string) $payload['printed_at'];
        $this->headers = $payload['headers'];
        $this->positions = $payload['positions'];

        $this->drawHeader();
        $xtop = 460.0;
        foreach ($payload['rows'] as $row) {
            foreach ($row as $index => $cell) {
                $x = (float) ($this->positions[$index] ?? 25);
                $align = 'left';
                $rightPad = 0.0;
                if ($cell['field'] === 'weiamt') {
                    $align = 'right';
                    $rightPad = 45.0;
                }
                $this->placeAligned($this->font, $this->fontSize, $x, $xtop, (string) $cell['value'], $align, $rightPad);
            }
            $xtop -= 12;
            if ($xtop < 20) {
                $this->canvas->new_page();
                $this->drawHeader();
                $xtop = 460.0;
            }
        }

        $baseline = $this->canvas->get_font_baseline($this->font, 8);
        $this->canvas->page_text(
            $this->lineWidth - 10,
            $this->pageHeight - 8 - $baseline,
            'Page {PAGE_NUM} of {PAGE_COUNT}',
            $this->font,
            8,
        );

        return $this->dompdf->output(['compress' => 1]);
    }

    private function drawHeader(): void
    {
        $xtop = 575.0;
        $this->placeText($this->bold, 14, 25, $xtop, $this->company);
        $xtop -= 18;
        $this->placeText($this->bold, $this->fontSize + 2, 25, $xtop, $this->title);
        $xtop -= 18;
        $this->placeText($this->font, $this->fontSize, 25, $xtop, 'Voyage No. : '.$this->voynum);
        $this->placeText($this->font, $this->fontSize, 725, $xtop, 'Sailing Date : '.$this->sailingDate);
        $xtop -= 18;
        $this->placeText($this->font, $this->fontSize, 25, $xtop, 'Cargo Category : '.$this->catcde);
        $this->placeText($this->font, $this->fontSize, 725, $xtop, 'Vessel : '.$this->vessel);
        $xtop -= 18;
        $this->placeText($this->font, $this->fontSize, 725, $xtop, 'Date Printed: ');
        $this->placeText($this->bold, $this->fontSize, 795, $xtop, $this->printedAt);
        $xtop -= 30;
        $barHeight = $this->fontSize + 5;
        $this->canvas->filled_rectangle(25, $this->pageHeight - $xtop - $barHeight, $this->lineWidth, $barHeight, [0.8, 0.8, 0.8]);
        $this->canvas->line(25, $this->pageHeight - $xtop, 25 + $this->lineWidth, $this->pageHeight - $xtop, [0, 0, 0], 0.5);
        foreach ($this->headers as $index => $header) {
            $this->placeText($this->bold, $this->fontSize, (float) ($this->positions[$index] ?? 25), $xtop + 5, $header);
        }
    }

    private function placeAligned(
        string $font,
        float $size,
        float $x,
        float $ezY,
        string $text,
        string $align,
        float $rightPad,
    ): void {
        $text = self::pdfText($text);
        if ($text === '') {
            return;
        }
        if ($align === 'right') {
            $width = $this->metrics->getTextWidth($text, $font, $size);
            $x = $x + $rightPad - $width;
        }
        $this->placeText($font, $size, $x, $ezY, $text);
    }

    private function placeText(string $font, float $size, float $x, float $ezY, string $text): void
    {
        $text = self::pdfText($text);
        if ($text === '') {
            return;
        }
        $canvasY = $this->pageHeight - $ezY - $this->canvas->get_font_baseline($font, $size);
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
