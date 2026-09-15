<?php

namespace App\Support;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;

class VanShipmentSummaryPdf
{
    private const PAGE_HEIGHT = 612.0;

    private const LEFT = 25.0;

    private const LINE_WIDTH = 760.0;

    private const FONT_SIZE = 9.0;

    private const BAR_HEIGHT = 14.0;

    private const BODY_TOP = 470.0;

    private const NAME_X = 25.0;

    private const VOYAGE_X = 250.0;

    private const BL_X = 340.0;

    public function __construct(
        private Canvas $canvas,
        private FontMetrics $metrics,
        private string $font,
        private string $bold,
        private Dompdf $dompdf,
        private string $company = '',
        private string $origin = '',
        private string $destination = '',
        private string $period = '',
        private string $printedAt = '',
        /** @var list<array{tag: string, header_x: float, pos: float}> */
        private array $categories = [],
        private float $totalHeaderX = 0.0,
        private float $totalX = 0.0,
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
     * @param  list<array{tag: string, catcde: string}>  $categories
     * @param  list<array{name: string, rows: list<array{voynum: string, docnum: string, counts: array<string, int>}>, subtotals: array<string, int>, line_total: int}>  $shippers
     * @param  list<array{name: string, rows: list<array{voynum: string, docnum: string, counts: array<string, int>}>, subtotals: array<string, int>, line_total: int}>  $consignees
     * @param  array<string, int>  $grandCounts
     */
    public function render(
        string $company,
        string $origin,
        string $destination,
        string $period,
        string $printedAt,
        array $categories,
        array $shippers,
        array $consignees,
        array $grandCounts,
        int $grandTotal,
    ): string {
        $this->company = $company;
        $this->origin = $origin;
        $this->destination = $destination;
        $this->period = $period;
        $this->printedAt = $printedAt;
        $this->layoutCategories($categories);

        $this->drawHeader();
        $xtop = self::BODY_TOP;
        $xtop = $this->drawParties($shippers, 80.0, $xtop);
        $xtop = $this->drawParties($consignees, 0.0, $xtop);
        $this->drawGrandTotal($xtop, $grandCounts, $grandTotal);

        $footerFontHeight = $this->canvas->get_font_baseline($this->font, 8);
        $footerY = self::PAGE_HEIGHT - 8 - $footerFontHeight;
        $this->canvas->page_text(self::LEFT, $footerY, 'Date Printed : '.$printedAt, $this->font, 8);
        $this->canvas->page_text(self::LINE_WIDTH - 10, $footerY, 'Page {PAGE_NUM} of {PAGE_COUNT}', $this->font, 8);

        return $this->dompdf->output(['compress' => 1]);
    }

    /**
     * @param  list<array{tag: string, catcde: string}>  $categories
     */
    private function layoutCategories(array $categories): void
    {
        $leftmost = self::BL_X + 90;
        $layout = [];
        foreach ($categories as $category) {
            $layout[] = [
                'tag' => $category['tag'],
                'header_x' => $leftmost + 80,
                'pos' => $leftmost + 25,
            ];
            $leftmost += 70;
        }
        $this->categories = $layout;
        $this->totalHeaderX = $leftmost + 110;
        $this->totalX = $leftmost + 130;
    }

    /**
     * @param  list<array{name: string, rows: list<array{voynum: string, docnum: string, counts: array<string, int>}>, subtotals: array<string, int>, line_total: int}>  $parties
     */
    private function drawParties(array $parties, float $countOffset, float $xtop): float
    {
        foreach ($parties as $party) {
            $first = true;
            foreach ($party['rows'] as $row) {
                if ($xtop < 50) {
                    $this->canvas->new_page();
                    $this->drawHeader();
                    $xtop = self::BODY_TOP;
                }
                if ($first) {
                    $this->placeText($this->font, self::FONT_SIZE, self::NAME_X, $xtop, $party['name']);
                    $first = false;
                }
                $this->placeText($this->font, self::FONT_SIZE, self::VOYAGE_X, $xtop, $row['voynum']);
                $this->placeText($this->font, self::FONT_SIZE, self::BL_X, $xtop, $row['docnum']);
                $lineTotal = 0;
                foreach ($this->categories as $category) {
                    $count = (int) ($row['counts'][$category['tag']] ?? 0);
                    $lineTotal += $count;
                    $this->placeRight($this->font, self::FONT_SIZE, $category['pos'] + $countOffset, $xtop, $this->intCount($count));
                }
                $this->placeRight($this->font, self::FONT_SIZE, $this->totalX, $xtop, $this->intCount($lineTotal));
                $xtop -= 12;
            }

            if ($party['rows'] !== []) {
                if ($xtop < 50) {
                    $this->canvas->new_page();
                    $this->drawHeader();
                    $xtop = self::BODY_TOP;
                }
                $this->canvas->line(
                    self::NAME_X,
                    self::PAGE_HEIGHT - ($xtop + 10),
                    self::LEFT + self::LINE_WIDTH,
                    self::PAGE_HEIGHT - ($xtop + 10),
                    [0, 0, 0],
                    0.5,
                );
                $this->placeText($this->font, self::FONT_SIZE, self::NAME_X, $xtop, 'Subtotal >>');
                foreach ($this->categories as $category) {
                    $count = (int) ($party['subtotals'][$category['tag']] ?? 0);
                    $this->placeRight($this->font, self::FONT_SIZE, $category['pos'] + $countOffset, $xtop, $this->intCount($count));
                }
                $this->placeRight($this->font, self::FONT_SIZE, $this->totalX, $xtop, $this->intCount((int) $party['line_total']));
                $xtop -= 16;
            }
        }

        return $xtop;
    }

    /**
     * @param  array<string, int>  $grandCounts
     */
    private function drawGrandTotal(float $xtop, array $grandCounts, int $grandTotal): void
    {
        if ($xtop < 50) {
            $this->canvas->new_page();
            $this->drawHeader();
            $xtop = self::BODY_TOP;
        }
        $this->canvas->line(
            self::LEFT,
            self::PAGE_HEIGHT - ($xtop + 10),
            self::LEFT + self::LINE_WIDTH,
            self::PAGE_HEIGHT - ($xtop + 10),
            [0, 0, 0],
            0.5,
        );
        $this->placeText($this->font, self::FONT_SIZE, self::NAME_X, $xtop, 'Grand Total >>');
        foreach ($this->categories as $category) {
            $count = (int) ($grandCounts[$category['tag']] ?? 0);
            $this->placeRight($this->font, self::FONT_SIZE, $category['pos'] + 80, $xtop, (string) $count);
        }
        $this->placeRight($this->font, self::FONT_SIZE, $this->totalX, $xtop, (string) $grandTotal);
    }

    private function drawHeader(): void
    {
        $xtop = 570.0;
        $this->placeText($this->bold, 11, self::LEFT, $xtop, strtoupper($this->company));
        $xtop -= 12;
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop, "Shipper's And Consignee's Shipment Summary ");
        $xtop -= 12;
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop, 'Origin : '.$this->origin);
        $xtop -= 12;
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop, 'Destination : '.$this->destination);
        $xtop -= 12;
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop, 'Period Covered : '.$this->period);
        $xtop -= 30;

        $this->canvas->filled_rectangle(
            self::LEFT,
            self::PAGE_HEIGHT - $xtop - self::BAR_HEIGHT,
            self::LINE_WIDTH,
            self::BAR_HEIGHT,
            [0.8, 0.8, 0.8],
        );
        $this->canvas->line(
            self::LEFT,
            self::PAGE_HEIGHT - $xtop,
            self::LEFT + self::LINE_WIDTH,
            self::PAGE_HEIGHT - $xtop,
            [0, 0, 0],
            0.5,
        );

        $this->placeText($this->bold, self::FONT_SIZE, self::NAME_X, $xtop + 5, 'Shipper/Consignee');
        $this->placeText($this->bold, self::FONT_SIZE, self::VOYAGE_X, $xtop + 5, 'Voyage #');
        $this->placeText($this->bold, self::FONT_SIZE, self::BL_X, $xtop + 5, 'BL #');
        foreach ($this->categories as $category) {
            $this->placeText($this->bold, 8, $category['header_x'], $xtop + 5, strtoupper($category['tag']));
        }
        $this->placeText($this->bold, 8, $this->totalHeaderX, $xtop + 5, 'TOTAL');
    }

    private function intCount(int $count): string
    {
        return $count > 0 ? number_format($count) : '';
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
