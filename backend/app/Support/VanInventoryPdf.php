<?php

namespace App\Support;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\Options;

class VanInventoryPdf
{
    private const PAGE_HEIGHT = 792.0;

    private const LEFT = 25.0;

    private const LINE_WIDTH = 570.0;

    private const FONT_SIZE = 9.0;

    private const BAR_HEIGHT = 14.0;

    /**
     * @param  list<string>  $headers
     * @param  list<int>  $positions
     * @param  iterable<int, array{0: int, 1: list<string>}>  $rows
     */
    public static function render(
        string $company,
        string $vanType,
        string $printedAt,
        array $headers,
        array $positions,
        iterable $rows,
    ): string {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $options = new Options;
        $options->setDefaultPaperSize('letter');
        $options->setDefaultPaperOrientation('portrait');
        $options->setDefaultFont('Helvetica');
        $options->setIsFontSubsettingEnabled(false);
        $options->setIsRemoteEnabled(false);

        $dompdf = new Dompdf($options);
        $canvas = $dompdf->getCanvas();
        $fonts = $dompdf->getFontMetrics();
        $font = $fonts->getFont('Helvetica') ?: 'Helvetica';
        $bold = $fonts->getFont('Helvetica', 'bold') ?: $font;

        self::drawHeader($canvas, $font, $bold, $company, $vanType, $headers, $positions);

        $xtop = 668.0;
        foreach ($rows as [$line, $cells]) {
            if ($xtop < 15) {
                $canvas->new_page();
                self::drawHeader($canvas, $font, $bold, $company, $vanType, $headers, $positions);
                $xtop = 630.0;
            }

            self::placeText($canvas, $font, self::FONT_SIZE, 30, $xtop, $line.'.');
            foreach ($cells as $index => $cell) {
                $x = $positions[$index] ?? self::LEFT;
                self::placeText($canvas, $font, self::FONT_SIZE, $x, $xtop, $cell);
            }
            $xtop -= 12;
        }

        $footerFontHeight = $canvas->get_font_baseline($font, 8);
        $footerY = self::PAGE_HEIGHT - 8 - $footerFontHeight;
        $canvas->page_text(self::LEFT, $footerY, 'Date Printed : '.$printedAt, $font, 8);
        $canvas->page_text(self::LINE_WIDTH - 10, $footerY, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8);

        return $dompdf->output(['compress' => 1]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<int>  $positions
     */
    private static function drawHeader(
        Canvas $canvas,
        string $font,
        string $bold,
        string $company,
        string $vanType,
        array $headers,
        array $positions,
    ): void {
        self::placeText($canvas, $bold, 14, self::LEFT, 750, $company);
        self::placeText($canvas, $bold, 10, self::LEFT, 732, 'Van Inventory Report');

        if ($vanType !== '') {
            self::placeText($canvas, $font, self::FONT_SIZE, self::LEFT, 714, 'Container Van Type : ');
            self::placeText($canvas, $bold, self::FONT_SIZE, self::LEFT + 150, 714, $vanType);
        }

        $barBottom = 684.0;
        $canvas->filled_rectangle(
            self::LEFT,
            self::PAGE_HEIGHT - $barBottom - self::BAR_HEIGHT,
            self::LINE_WIDTH,
            self::BAR_HEIGHT,
            [0.8, 0.8, 0.8],
        );
        $lineY = self::PAGE_HEIGHT - $barBottom;
        $canvas->line(self::LEFT, $lineY, self::LEFT + self::LINE_WIDTH, $lineY, [0, 0, 0], 0.5);

        foreach ($headers as $index => $header) {
            $x = $positions[$index] ?? self::LEFT;
            self::placeText($canvas, $bold, self::FONT_SIZE, $x, $barBottom + 5, $header);
        }
    }

    private static function placeText(
        Canvas $canvas,
        string $font,
        float $size,
        float $x,
        float $ezY,
        string $text,
    ): void {
        $canvasY = self::PAGE_HEIGHT - $ezY - $canvas->get_font_baseline($font, $size);
        $canvas->text($x, $canvasY, self::pdfText($text), $font, $size);
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
