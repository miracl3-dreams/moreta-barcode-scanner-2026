<?php

namespace App\Support;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;

class SummaryPdf
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
        private string $company = '',
        private string $printedAt = '',
        private string $subtitle = '',
        private string $filterLabel = '',
        private string $filterValue = '',
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
     * @param  list<array{voynum: string, rows: list<array{payee: string, docnum: string, shipmode: string, trntot: float}>}>  $groups
     */
    public function byPayment(
        string $company,
        string $paytrmcde,
        string $payeeHeader,
        string $printedAt,
        array $groups,
    ): string {
        $this->company = $company;
        $this->printedAt = $printedAt;
        $this->subtitle = 'Summary by Payment Type';
        $this->filterLabel = 'Payment Type : ';
        $this->filterValue = $paytrmcde;
        $this->headers = [$payeeHeader, 'BL No.', 'MOS', 'Amount'];
        $this->positions = [50, 290, 430, 550];

        $first = true;
        foreach ($groups as $group) {
            if ($first) {
                $first = false;
                $this->drawHeader();
            } else {
                $this->canvas->new_page();
                $this->drawHeader();
            }
            $this->placeText($this->bold, self::FONT_SIZE, self::LEFT + 90, 714, $group['voynum']);
            $xtop = 650.0;
            $line = 1;
            $total = 0.0;
            foreach ($group['rows'] as $row) {
                $wrapped = explode("\n", wordwrap($row['payee'], 30, "\n"));
                $wrapCount = max(1, count($wrapped));
                $lineHeight = 12 + max(0, $wrapCount - 1) * 10;
                $this->placeText($this->font, self::FONT_SIZE, 30, $xtop, $line.'.');
                foreach ($wrapped as $index => $part) {
                    $this->placeText($this->font, self::FONT_SIZE, (float) $this->positions[0], $xtop - ($index * 10), $part);
                }
                $this->placeText($this->font, self::FONT_SIZE, (float) $this->positions[1], $xtop, $row['docnum']);
                $this->placeRight($this->font, self::FONT_SIZE, (float) $this->positions[2] + 30, $xtop, $row['shipmode']);
                $this->placeRight($this->font, self::FONT_SIZE, (float) $this->positions[3] + 30, $xtop, number_format($row['trntot'], 2));
                $total += $row['trntot'];
                $xtop -= $lineHeight;
                $line++;
                if ($xtop < 15) {
                    $this->canvas->new_page();
                    $this->drawHeader();
                    $xtop = 630.0;
                }
            }
            $this->canvas->line(
                self::LEFT,
                self::PAGE_HEIGHT - $xtop,
                self::LEFT + self::LINE_WIDTH,
                self::PAGE_HEIGHT - $xtop,
                [0, 0, 0],
                0.5,
            );
            $xtop -= 12;
            $this->placeText($this->bold, self::FONT_SIZE, (float) $this->positions[0], $xtop, 'Total Amount');
            $this->placeRight($this->font, self::FONT_SIZE, (float) $this->positions[3] + 30, $xtop, number_format($total, 2));
        }

        if ($first) {
            $this->drawHeader();
        }

        return $this->dompdf->output(['compress' => 1]);
    }

    /**
     * @param  list<array{docnum: string, cuscde: string, concde: string, vannum: string, weiamt: string, remarks: string, shipmode: string}>  $rows
     */
    public function byCargo(string $company, string $catcde, string $printedAt, array $rows): string
    {
        $this->company = $company;
        $this->printedAt = $printedAt;
        $this->subtitle = 'Summary by Cargo Type';
        $this->filterLabel = 'Cargo Type : ';
        $this->filterValue = $catcde;
        $this->headers = ['B/L #', 'Shipper', 'Consignee', 'Van #', 'Weight', 'Remarks', 'MOS'];
        $this->positions = [45, 115, 215, 320, 410, 455, 550];

        $this->drawHeader();
        $xtop = 650.0;
        $line = 1;
        foreach ($rows as $row) {
            $this->placeText($this->font, self::FONT_SIZE, 25, $xtop, $line.'.');
            $this->placeText($this->font, self::FONT_SIZE, (float) $this->positions[0], $xtop, $row['docnum']);
            $this->placeText($this->font, self::FONT_SIZE, (float) $this->positions[1], $xtop, $row['cuscde']);
            $this->placeText($this->font, self::FONT_SIZE, (float) $this->positions[2], $xtop, $row['concde']);
            $this->placeText($this->font, self::FONT_SIZE, (float) $this->positions[3], $xtop, $row['vannum']);
            $this->placeRight($this->font, self::FONT_SIZE, (float) $this->positions[4] + 30, $xtop, $row['weiamt']);
            $this->placeText($this->font, self::FONT_SIZE, (float) $this->positions[5], $xtop, $row['remarks']);
            $this->placeText($this->font, self::FONT_SIZE - 2, (float) $this->positions[6], $xtop, $row['shipmode']);
            $line++;
            $xtop -= 12;
            if ($xtop < 15) {
                $this->canvas->new_page();
                $this->drawHeader();
                $xtop = 650.0;
            }
        }

        return $this->dompdf->output(['compress' => 1]);
    }

    private function drawHeader(): void
    {
        $xtop = 750.0;
        $this->placeText($this->bold, 14, self::LEFT, $xtop, $this->company);
        $xtop -= 18;
        $this->placeText($this->bold, self::FONT_SIZE + 1, self::LEFT, $xtop, $this->subtitle);
        $xtop -= 18;
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop, 'Voyage No. : ');
        $xtop -= 18;
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop, $this->filterLabel);
        $offset = $this->filterLabel === 'Payment Type : ' ? 80.0 : 100.0;
        if ($this->filterLabel === 'Payment Type : ') {
            $this->placeText($this->bold, self::FONT_SIZE, self::LEFT + $offset, $xtop, $this->filterValue);
        } else {
            $this->placeText($this->font, self::FONT_SIZE, self::LEFT + $offset, $xtop, $this->filterValue);
        }
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT + 360, $xtop, 'Date Printed: ');
        $this->placeText($this->bold, self::FONT_SIZE, self::LEFT + 460, $xtop, $this->printedAt);
        $xtop -= 30;
        $this->canvas->filled_rectangle(
            self::LEFT,
            self::PAGE_HEIGHT - $xtop - self::BAR_HEIGHT,
            self::LINE_WIDTH,
            self::BAR_HEIGHT,
            [0.8, 0.8, 0.8],
        );
        $lineY = self::PAGE_HEIGHT - $xtop;
        $this->canvas->line(self::LEFT, $lineY, self::LEFT + self::LINE_WIDTH, $lineY, [0, 0, 0], 0.5);
        foreach ($this->headers as $index => $header) {
            $this->placeText($this->bold, self::FONT_SIZE, (float) ($this->positions[$index] ?? self::LEFT), $xtop + 5, $header);
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
