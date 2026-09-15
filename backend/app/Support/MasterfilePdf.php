<?php

namespace App\Support;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;

class MasterfilePdf
{
    private const PAGE_HEIGHT = 792.0;

    /** pdf_mf.php first-page body Y (660). Do not reset to the header line at 680. */
    private const BODY_TOP = 660.0;

    /**
     * @param  list<string>  $headers
     * @param  list<int>  $positions
     * @param  list<int>  $maxLengths
     * @param  iterable<int, list<string>>  $rows
     */
    public static function render(
        string $company,
        string $title,
        array $headers,
        array $positions,
        array $maxLengths,
        iterable $rows,
        string $filename,
    ): Response {
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
        $printedAt = now('Asia/Manila')->format('F j, Y, g:i A');

        self::drawHeader($canvas, $font, $bold, $company, $title, $headers, $positions);

        $xtop = self::BODY_TOP;
        foreach ($rows as $cells) {
            if ($xtop < 30) {
                $canvas->new_page();
                self::drawHeader($canvas, $font, $bold, $company, $title, $headers, $positions);
                $xtop = self::BODY_TOP;
            }

            foreach ($cells as $index => $cell) {
                $x = $positions[$index] ?? 30;
                $limit = $maxLengths[$index] ?? 25;
                self::placeText($canvas, $font, 10, $x, $xtop, self::ellipsis($cell, $limit));
            }
            $xtop -= 15;
        }

        $footerFontHeight = $canvas->get_font_baseline($font, 8);
        $footerY = self::PAGE_HEIGHT - 15 - $footerFontHeight;
        $canvas->page_text(30, $footerY, 'Date Printed : '.$printedAt, $font, 8);
        $canvas->page_text(500, $footerY, 'Page {PAGE_NUM}  of  {PAGE_COUNT}', $font, 8);

        return new Response(
            $dompdf->output(['compress' => 1]),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ],
        );
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
        string $title,
        array $headers,
        array $positions,
    ): void {
        self::placeText($canvas, $bold, 14, 30, 750, $company);
        self::placeText($canvas, $font, 10, 30, 730, $title);

        foreach ($headers as $index => $header) {
            $x = $positions[$index] ?? 30;
            self::placeText($canvas, $bold, 10, $x, 687, $header);
        }

        $canvas->line(25, self::PAGE_HEIGHT - 700, 585, self::PAGE_HEIGHT - 700, [0, 0, 0], 0.5);
        $canvas->line(25, self::PAGE_HEIGHT - 680, 585, self::PAGE_HEIGHT - 680, [0, 0, 0], 0.5);
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

    private static function ellipsis(string $text, int $length): string
    {
        $text = trim($text);
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, max(0, $length - 2)).'..';
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
