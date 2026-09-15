<?php

namespace App\Support;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;

class VanUsagePdf
{
    private const PAGE_HEIGHT = 792.0;

    private const LEFT = 25.0;

    private const LINE_WIDTH = 570.0;

    private const FONT_SIZE = 9.0;

    private const BAR_HEIGHT = 14.0;

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

    /**
     * @param  iterable<int, array{0: int, 1: list<string>}>  $rows
     */
    public function summary(
        string $company,
        string $periodFrom,
        string $periodTo,
        string $printedAt,
        iterable $rows,
    ): string {
        $headers = ['Van #', 'Sailing Date', 'Origin', 'Destination', 'Voyage #', 'BL #'];
        $positions = [50, 120, 180, 280, 375, 470];

        $this->drawSummaryHeader($company, $periodFrom, $periodTo, $printedAt, $headers, $positions);

        $xtop = 664.0;
        $total = 0;
        foreach ($rows as [$line, $cells]) {
            if ($xtop < 15) {
                $this->canvas->new_page();
                $this->drawSummaryHeader($company, $periodFrom, $periodTo, $printedAt, $headers, $positions);
                $xtop = 660.0;
            }

            $this->placeText($this->font, self::FONT_SIZE, 30, $xtop, $line.'.');
            foreach ($cells as $index => $cell) {
                $this->placeText($this->font, self::FONT_SIZE, $positions[$index] ?? self::LEFT, $xtop, $cell);
            }
            $xtop -= 12;
            $total = $line;
        }

        $this->drawTotal($xtop, $total);

        return $this->output();
    }

    /**
     * @param  iterable<int, object>  $rows
     */
    public function byLocation(
        string $company,
        string $origin,
        string $printedAt,
        iterable $rows,
    ): string {
        $headers = ['Voynum', 'Shipper', 'Van #', 'Description', 'Remarks'];
        $headerPositions = [24, 99, 209, 284, 429];
        $positions = [25, 100, 210, 285, 430];
        $all = $origin === 'ALL';

        $this->drawLocationHeader($company, $origin, $printedAt, $headers, $headerPositions);

        $xtop = 670.0;
        $line = 0;
        $check = 'xx';
        foreach ($rows as $row) {
            if ($xtop < 50) {
                $this->canvas->new_page();
                $this->drawLocationHeader($company, $origin, $printedAt, $headers, $headerPositions);
                $xtop = 630.0;
            }

            $lastloc = trim((string) ($row->lastloc ?? ''));
            if ($all && $check !== $lastloc) {
                $label = $lastloc === '' ? 'Not Assigned' : $lastloc;
                $this->placeText($this->bold, self::FONT_SIZE, 30, $xtop, strtoupper($label));
                $xtop -= 12;
                $check = $lastloc;
            }

            $wrapYs = [];
            $this->placeText($this->font, self::FONT_SIZE, $positions[0], $xtop, (string) ($row->voynum ?? ''));
            $wrapYs[] = $this->placeWrapped($positions[1], $xtop, (string) ($row->cuscde ?? ''), 100);
            $this->placeText($this->font, self::FONT_SIZE, $positions[2], $xtop, (string) ($row->prevannum ?? ''));
            $this->placeText($this->font, self::FONT_SIZE, $positions[3], $xtop, (string) ($row->vandsc ?? ''));
            $wrapYs[] = $this->placeWrapped($positions[4], $xtop, (string) ($row->remarks ?? ''), 100);

            $line++;
            $xtop -= 12;
            $xtop = min(array_map(static fn ($y) => $y - 12, $wrapYs));
        }

        $this->drawTotal($xtop, $line);

        return $this->output();
    }

    /**
     * @param  iterable<int, array{0: int, 1: list<string>}>  $rows
     */
    public function byVanNumber(
        string $company,
        string $periodFrom,
        string $periodTo,
        string $vannum,
        string $location,
        string $printedAt,
        iterable $rows,
        bool $showLocation,
    ): string {
        $headers = ['Date', 'Voyage #', 'BL #', 'Shipper', 'Origin', 'Destination'];
        $positions = [50, 100, 180, 290, 440, 520];

        $this->drawVanNumberHeader(
            $company,
            $periodFrom,
            $periodTo,
            $vannum,
            $location,
            $printedAt,
            $headers,
            $positions,
            $showLocation,
        );

        $xtop = 650.0;
        foreach ($rows as [$line, $cells]) {
            if ($xtop < 15) {
                $this->canvas->new_page();
                $this->drawVanNumberHeader(
                    $company,
                    $periodFrom,
                    $periodTo,
                    $vannum,
                    $location,
                    $printedAt,
                    $headers,
                    $positions,
                    $showLocation,
                );
                $xtop = 630.0;
            }

            $this->placeText($this->font, self::FONT_SIZE, 30, $xtop, $line.'.');
            foreach ($cells as $index => $cell) {
                $this->placeText($this->font, self::FONT_SIZE, $positions[$index] ?? self::LEFT, $xtop, $cell);
            }
            $xtop -= 12;
        }

        $footerFontHeight = $this->canvas->get_font_baseline($this->font, 8);
        $footerY = self::PAGE_HEIGHT - 8 - $footerFontHeight;
        $this->canvas->page_text(590, $footerY, 'Page {PAGE_NUM} of {PAGE_COUNT}', $this->font, 8);

        return $this->output();
    }

    /**
     * @param  list<string>  $headers
     * @param  list<int>  $positions
     */
    private function drawSummaryHeader(
        string $company,
        string $periodFrom,
        string $periodTo,
        string $printedAt,
        array $headers,
        array $positions,
    ): void {
        $this->placeText($this->bold, 14, self::LEFT, 750, $company);
        $this->placeText($this->bold, 10, self::LEFT, 732, 'Van Usage Report');
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, 714, 'Period Covered : '.$periodFrom.' - '.$periodTo);
        $this->placePrinted($printedAt, 714);
        $this->drawBar(684, $headers, $positions);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<int>  $positions
     */
    private function drawLocationHeader(
        string $company,
        string $origin,
        string $printedAt,
        array $headers,
        array $positions,
    ): void {
        $this->placeText($this->bold, 14, self::LEFT, 750, $company);
        $this->placeText($this->bold, 10, self::LEFT, 732, 'Van Usage By Location');
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, 714, 'Location : '.$origin);
        $this->placePrinted($printedAt, 714);
        $this->drawBar(684, $headers, $positions);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<int>  $positions
     */
    private function drawVanNumberHeader(
        string $company,
        string $periodFrom,
        string $periodTo,
        string $vannum,
        string $location,
        string $printedAt,
        array $headers,
        array $positions,
        bool $showLocation,
    ): void {
        $this->placeText($this->bold, 14, self::LEFT, 750, $company);
        $this->placeText($this->bold, 10, self::LEFT, 732, 'Van Usage By Van Number');
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, 714, 'Period Covered : '.$periodFrom.' - '.$periodTo);
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, 696, 'Van Number : ');
        $this->placeText($this->bold, self::FONT_SIZE, self::LEFT + 60, 696, $vannum);
        $this->placePrinted($printedAt, 696);

        if ($showLocation) {
            $this->placeText($this->font, self::FONT_SIZE, self::LEFT, 678, 'Location : ');
            $this->placeText($this->bold, self::FONT_SIZE, self::LEFT + 60, 678, $location);
            $this->drawBar(648, $headers, $positions);

            return;
        }

        $this->drawBar(666, $headers, $positions);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<int>  $positions
     */
    private function drawBar(float $barBottom, array $headers, array $positions): void
    {
        $this->canvas->filled_rectangle(
            self::LEFT,
            self::PAGE_HEIGHT - $barBottom - self::BAR_HEIGHT,
            self::LINE_WIDTH,
            self::BAR_HEIGHT,
            [0.8, 0.8, 0.8],
        );
        $lineY = self::PAGE_HEIGHT - $barBottom;
        $this->canvas->line(self::LEFT, $lineY, self::LEFT + self::LINE_WIDTH, $lineY, [0, 0, 0], 0.5);

        foreach ($headers as $index => $header) {
            $this->placeText($this->bold, self::FONT_SIZE, $positions[$index] ?? self::LEFT, $barBottom + 5, $header);
        }
    }

    private function placePrinted(string $printedAt, float $ezY): void
    {
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT + 360, $ezY, 'Date Printed: ');
        $this->placeText($this->bold, self::FONT_SIZE, self::LEFT + 460, $ezY, $printedAt);
    }

    private function drawTotal(float $xtop, int $total): void
    {
        $this->canvas->line(
            self::LEFT,
            self::PAGE_HEIGHT - $xtop,
            self::LEFT + self::LINE_WIDTH,
            self::PAGE_HEIGHT - $xtop,
            [0, 0, 0],
            0.5,
        );
        $this->canvas->line(
            self::LEFT,
            self::PAGE_HEIGHT - ($xtop - 3),
            self::LEFT + self::LINE_WIDTH,
            self::PAGE_HEIGHT - ($xtop - 3),
            [0, 0, 0],
            0.5,
        );
        $xtop -= 15;
        $this->placeText($this->bold, 10, 400, $xtop, 'TOTAL Number of Van ');
        $this->placeText($this->bold, 10, 580, $xtop, (string) $total);
        $xtop -= 10;
        $this->canvas->line(
            self::LEFT,
            self::PAGE_HEIGHT - $xtop,
            self::LEFT + self::LINE_WIDTH,
            self::PAGE_HEIGHT - $xtop,
            [0, 0, 0],
            0.5,
        );
    }

    private function placeWrapped(float $x, float $ezY, string $text, float $width): float
    {
        $text = self::pdfText($text);
        if ($text === '') {
            return $ezY;
        }

        $top = $ezY;
        while ($text !== '') {
            $fit = $text;
            while ($this->metrics->getTextWidth($fit, $this->font, self::FONT_SIZE) > $width && mb_strlen($fit) > 1) {
                $fit = mb_substr($fit, 0, -1);
            }
            $this->placeText($this->font, self::FONT_SIZE, $x, $top, $fit);
            $text = mb_substr($text, mb_strlen($fit));
            if ($text !== '') {
                $top -= 12;
            }
        }

        return $top;
    }

    private function placeText(string $font, float $size, float $x, float $ezY, string $text): void
    {
        $canvasY = self::PAGE_HEIGHT - $ezY - $this->canvas->get_font_baseline($font, $size);
        $this->canvas->text($x, $canvasY, self::pdfText($text), $font, $size);
    }

    private function output(): string
    {
        return $this->dompdf->output(['compress' => 1]);
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
