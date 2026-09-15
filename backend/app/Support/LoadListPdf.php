<?php

namespace App\Support;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Http\Response;

class LoadListPdf
{
    public function __construct(
        private Canvas $canvas,
        private FontMetrics $metrics,
        private string $font,
        private string $bold,
        private Dompdf $dompdf,
        private float $pageHeight,
        private float $fontSize,
        private float $lineWidth,
        private float $headerTop,
        private float $bodyTop,
        private float $metaX,
        private float $dateValueX,
        private bool $pageNumbers,
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

    public static function office(): self
    {
        return self::make(936.0, 612.0, 9.0, 890.0, 580.0, 460.0, 625.0, 695.0, true);
    }

    public static function checker(): self
    {
        return self::make(612.0, 936.0, 11.0, 570.0, 900.0, 785.0, 425.0, 495.0, false);
    }

    public static function errorResponse(string $message): Response
    {
        $pdf = self::make(612.0, 792.0, 9.0, 570.0, 750.0, 650.0, 385.0, 485.0, false);
        $pdf->placeText($pdf->bold, 12, 25, 750, $message);

        return new Response($pdf->dompdf->output(['compress' => 1]), 422, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="load-list-error.pdf"',
        ]);
    }

    private static function make(
        float $width,
        float $height,
        float $fontSize,
        float $lineWidth,
        float $headerTop,
        float $bodyTop,
        float $metaX,
        float $dateValueX,
        bool $pageNumbers,
    ): self {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $options = new Options;
        $options->setDefaultPaperSize([0, 0, $width, $height]);
        $options->setDefaultFont('Helvetica');
        $options->setIsFontSubsettingEnabled(false);
        $options->setIsRemoteEnabled(false);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper([0, 0, $width, $height]);
        $fonts = $dompdf->getFontMetrics();
        $font = $fonts->getFont('Helvetica') ?: 'Helvetica';
        $bold = $fonts->getFont('Helvetica', 'bold') ?: $font;

        return new self(
            $dompdf->getCanvas(),
            $fonts,
            $font,
            $bold,
            $dompdf,
            $height,
            $fontSize,
            $lineWidth,
            $headerTop,
            $bodyTop,
            $metaX,
            $dateValueX,
            $pageNumbers,
        );
    }

    /**
     * @param  array{
     *     copy: string,
     *     company: string,
     *     voynum: string,
     *     catcde: string,
     *     sailing_date: string,
     *     vessel: string,
     *     printed_at: string,
     *     headers: list<string>,
     *     positions: list<int>,
     *     rows: list<list<array{value: string, field: string}>>
     * }  $payload
     */
    public function render(array $payload): string
    {
        $this->company = $payload['company'];
        $this->title = $payload['copy'] === 'checker' ? 'Load List (CHECKER COPY)' : 'Load List (OFFICE COPY)';
        $this->voynum = $payload['voynum'];
        $this->catcde = $payload['catcde'];
        $this->sailingDate = $payload['sailing_date'];
        $this->vessel = $payload['vessel'];
        $this->printedAt = $payload['printed_at'];
        $this->headers = $payload['headers'];
        $this->positions = $payload['positions'];

        $this->drawHeader();
        $xtop = $this->bodyTop;
        foreach ($payload['rows'] as $row) {
            foreach ($row as $index => $cell) {
                $field = $cell['field'];
                $value = $cell['value'];
                $x = (float) ($this->positions[$index] ?? 25);
                $size = $this->fontSize;
                $align = 'left';
                $rightPad = 0.0;
                if ($field === 'weiamt') {
                    $align = 'right';
                    $rightPad = $payload['copy'] === 'checker' ? 40.0 : 50.0;
                }
                if ($field === 'vannum') {
                    $size = 13.0;
                }
                $this->placeAligned($this->font, $size, $x, $xtop, $value, $align, $rightPad);
            }
            $xtop -= 12;
            if ($xtop < 20) {
                $this->canvas->new_page();
                $this->drawHeader();
                $xtop = $this->bodyTop;
            }
        }

        if ($this->pageNumbers) {
            $baseline = $this->canvas->get_font_baseline($this->font, 8);
            $this->canvas->page_text(
                840,
                $this->pageHeight - 8 - $baseline,
                'Page {PAGE_NUM} of {PAGE_COUNT}',
                $this->font,
                8,
            );
        }

        return $this->dompdf->output(['compress' => 1]);
    }

    private function drawHeader(): void
    {
        $xtop = $this->headerTop;
        $this->placeText($this->bold, 14, 25, $xtop, $this->company);
        $xtop -= 18;
        $this->placeText($this->bold, $this->fontSize + 2, 25, $xtop, $this->title);
        $xtop -= 18;
        $this->placeText($this->font, $this->fontSize, 25, $xtop, 'Voyage No. : '.$this->voynum);
        $this->placeText($this->font, $this->fontSize, $this->metaX, $xtop, 'Sailing Date : '.$this->sailingDate);
        $xtop -= 18;
        $this->placeText($this->font, $this->fontSize, 25, $xtop, 'Cargo Category : '.$this->catcde);
        $this->placeText($this->font, $this->fontSize, $this->metaX, $xtop, 'Vessel : '.$this->vessel);
        $xtop -= 18;
        $this->placeText($this->font, $this->fontSize, $this->metaX, $xtop, 'Date Printed: ');
        $this->placeText($this->bold, $this->fontSize, $this->dateValueX, $xtop, $this->printedAt);
        $xtop -= 30;

        $barHeight = $this->fontSize + 5;
        $this->canvas->filled_rectangle(
            25,
            $this->pageHeight - $xtop - $barHeight,
            $this->lineWidth,
            $barHeight,
            [0.8, 0.8, 0.8],
        );
        $lineY = $this->pageHeight - $xtop;
        $this->canvas->line(25, $lineY, 25 + $this->lineWidth, $lineY, [0, 0, 0], 0.5);
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
        $canvasY = $this->pageHeight - $ezY - $this->canvas->get_font_baseline($font, $size);
        $this->canvas->text($x, $canvasY, self::pdfText($text), $font, $size);
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
