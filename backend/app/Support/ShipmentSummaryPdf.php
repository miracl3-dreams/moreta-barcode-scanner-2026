<?php

namespace App\Support;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;

class ShipmentSummaryPdf
{
    private const PAGE_HEIGHT = 612.0;

    private const LEFT = 20.0;

    private const LINE_WIDTH = 770.0;

    private const FONT_SIZE = 10.0;

    private const BAR_HEIGHT = 15.0;

    private const BODY_TOP = 520.0;

    /** @var list<int> */
    private const COLS = [20, 80, 200, 335, 480, 600, 630];

    private string $company = '';

    private string $title = '';

    private string $payeeLine = '';

    private string $periodLine = '';

    private string $printedAt = '';

    public function __construct(
        private Canvas $canvas,
        private FontMetrics $metrics,
        private string $font,
        private string $bold,
        private Dompdf $dompdf,
    ) {}

    public static function make(): self
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $options = new Options;
        $options->setDefaultPaperSize('letter');
        $options->setDefaultPaperOrientation('landscape');
        $options->setDefaultFont('Helvetica');
        $options->setIsFontSubsettingEnabled(false);
        $options->setIsRemoteEnabled(false);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('letter', 'landscape');
        $fonts = $dompdf->getFontMetrics();
        $font = $fonts->getFont('Helvetica') ?: 'Helvetica';
        $bold = $fonts->getFont('Helvetica', 'bold') ?: $font;

        return new self($dompdf->getCanvas(), $fonts, $font, $bold, $dompdf);
    }

    /**
     * @param  list<array{groups: list<array{label: string, rows: list<array{
     *     date: string, voyage: string, docnum: string, category: string,
     *     van: string, qty: string, description: string, show_bl: bool
     * }>}>}>  $payers
     */
    public function render(
        string $company,
        string $title,
        string $payeeLine,
        string $periodLine,
        string $printedAt,
        array $payers,
    ): string {
        $first = true;
        foreach ($payers as $payer) {
            if (! $first) {
                $this->canvas->new_page();
            }
            $first = false;
            $this->drawHeader($company, $title, $payeeLine, $periodLine, $printedAt);
            $this->drawPayer($payer['groups']);
        }

        if ($first) {
            $this->drawHeader($company, $title, $payeeLine, $periodLine, $printedAt);
        }

        $footerFontHeight = $this->canvas->get_font_baseline($this->font, 8);
        $footerY = self::PAGE_HEIGHT - 8 - $footerFontHeight;
        $this->canvas->page_text(self::LEFT + 10, $footerY, 'Date Printed : '.$printedAt, $this->font, 8);
        $this->canvas->page_text(self::LINE_WIDTH - 10, $footerY, 'Page {PAGE_NUM} of {PAGE_COUNT}', $this->font, 8);

        return $this->dompdf->output(['compress' => 1]);
    }

    /**
     * @param  list<array{label: string, rows: list<array{
     *     date: string, voyage: string, docnum: string, category: string,
     *     van: string, qty: string, description: string, show_bl: bool
     * }>}>  $groups
     */
    private function drawPayer(array $groups): void
    {
        $xtop = self::BODY_TOP;
        foreach ($groups as $group) {
            if ($xtop <= 50) {
                $this->canvas->new_page();
                $this->drawHeaderOnCurrentPage();
                $xtop = self::BODY_TOP;
            }

            $headerY = $xtop;
            $xtop -= 20;
            $headerDrawn = false;

            foreach ($group['rows'] as $row) {
                if ($xtop <= 50) {
                    $this->canvas->new_page();
                    $this->drawHeaderOnCurrentPage();
                    $xtop = self::BODY_TOP;
                }

                if (! $headerDrawn) {
                    $this->placeText($this->bold, self::FONT_SIZE, self::COLS[0], $headerY, $group['label']);
                    $this->canvas->line(
                        self::COLS[0],
                        self::PAGE_HEIGHT - ($headerY - 2),
                        self::LINE_WIDTH,
                        self::PAGE_HEIGHT - ($headerY - 2),
                        [0, 0, 0],
                        0.5,
                    );
                    $headerDrawn = true;
                }

                if ($row['show_bl']) {
                    $this->placeText($this->font, self::FONT_SIZE, self::COLS[0], $xtop, $row['date']);
                    $this->placeText($this->font, self::FONT_SIZE, self::COLS[1], $xtop, $row['voyage']);
                    $this->placeText($this->font, self::FONT_SIZE, self::COLS[2], $xtop, $row['docnum']);
                }

                $this->placeText($this->font, self::FONT_SIZE, self::COLS[3], $xtop, $row['category']);
                $this->placeText($this->font, self::FONT_SIZE, self::COLS[4], $xtop, $row['van']);
                $this->placeRight($this->font, self::FONT_SIZE, self::COLS[5] + 15, $xtop, $row['qty']);
                $this->placeText($this->font, self::FONT_SIZE, self::COLS[6], $xtop, $row['description']);
                $xtop -= 12;
            }

            $xtop -= 5;
        }
    }

    private function drawHeaderOnCurrentPage(): void
    {
        $this->drawHeader(
            $this->company,
            $this->title,
            $this->payeeLine,
            $this->periodLine,
            $this->printedAt,
        );
    }

    private function drawHeader(
        string $company,
        string $title,
        string $payeeLine,
        string $periodLine,
        string $printedAt,
    ): void {
        $this->company = $company;
        $this->title = $title;
        $this->payeeLine = $payeeLine;
        $this->periodLine = $periodLine;
        $this->printedAt = $printedAt;

        $xtop = 590.0;
        $this->placeText($this->bold, 12, self::LEFT, $xtop, strtoupper($company));
        $xtop -= 12;
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop, "Shipper's And Consignee's Shipment Summary (".$title.')');
        $xtop -= 12;
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop, $payeeLine);
        $xtop -= 12;
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop, $periodLine);
        $xtop -= 20;

        $this->canvas->filled_rectangle(
            self::LEFT,
            self::PAGE_HEIGHT - $xtop - self::BAR_HEIGHT,
            self::LINE_WIDTH - 20,
            self::BAR_HEIGHT,
            [0.8, 0.8, 0.8],
        );
        $this->canvas->line(
            self::LEFT,
            self::PAGE_HEIGHT - $xtop,
            self::LINE_WIDTH,
            self::PAGE_HEIGHT - $xtop,
            [0, 0, 0],
            0.5,
        );

        $xtop += 5;
        foreach (['Sailing Date', 'Voyage #', 'BL #', 'Category', 'Van #', 'Qty.', 'Description'] as $index => $header) {
            $this->placeText($this->bold, self::FONT_SIZE, self::COLS[$index], $xtop, $header);
        }
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
        $canvasY = self::PAGE_HEIGHT - $ezY - $this->canvas->get_font_baseline($font, $size);
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
