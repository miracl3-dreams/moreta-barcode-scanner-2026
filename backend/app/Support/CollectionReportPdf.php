<?php

namespace App\Support;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;

class CollectionReportPdf
{
    private const PAGE_WIDTH = 612.0;

    private const PAGE_HEIGHT = 936.0;

    private const FONT_SIZE = 9.0;

    private const LEFT = 10.0;

    private const LINE_WIDTH = 590.0;

    private const BODY_TOP = 820.0;

    public function __construct(
        private Canvas $canvas,
        private FontMetrics $metrics,
        private string $font,
        private string $bold,
        private Dompdf $dompdf,
        private array $payload = [],
        /** @var list<int> */
        private array $widths = [],
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

    /**
     * @param  array<string, mixed>  $payload
     */
    public function render(array $payload): string
    {
        $this->payload = $payload;
        $this->widths = $payload['widths'];
        $this->drawHeader();
        $xtop = self::BODY_TOP;
        foreach ($payload['groups'] as $group) {
            $first = true;
            foreach ($group['lines'] as $line) {
                if ($xtop <= 50) {
                    $this->canvas->new_page();
                    $this->drawHeader();
                    $xtop = 810.0;
                }
                if ($first) {
                    $this->placeText($this->font, self::FONT_SIZE, self::LEFT + $this->widths[0], $xtop, $group['header']);
                    $first = false;
                }
                $this->placeText($this->font, self::FONT_SIZE, self::LEFT + $this->widths[1], $xtop, $line['ornum']);
                $this->placeText($this->font, self::FONT_SIZE, self::LEFT + $this->widths[2], $xtop, $line['voynum']);
                $this->placeText($this->font, self::FONT_SIZE, self::LEFT + $this->widths[3], $xtop, $line['bolnum']);
                $this->placeText($this->font, self::FONT_SIZE, self::LEFT + $this->widths[4], $xtop, $line['col5']);
                $this->placeRight($this->font, self::FONT_SIZE, self::LEFT + $this->widths[5] + 30, $xtop, number_format((float) $line['amount'], 2));
                if ($line['tax'] !== null) {
                    $xtop -= 15;
                    $this->placeRight($this->font, self::FONT_SIZE, self::LEFT + $this->widths[4] + 50, $xtop, 'Less w/ tax');
                    $this->placeRight($this->font, self::FONT_SIZE, self::LEFT + $this->widths[5] + 30, $xtop, number_format((float) $line['tax'], 2));
                }
                $xtop -= 12;
            }
            $xtop -= 6;
            if ($xtop <= 50) {
                $this->canvas->new_page();
                $this->drawHeader();
                $xtop = 810.0;
            }
            $this->placeRight($this->font, self::FONT_SIZE, self::LEFT + $this->widths[5] - 100, $xtop, $group['remark']);
            $this->placeRight($this->font, self::FONT_SIZE, self::LEFT + $this->widths[6] + 20, $xtop, number_format((float) $group['total'], 2));
            $xtop -= 12;
        }

        $xtop += 10;
        $this->canvas->line(self::LEFT + 500, self::PAGE_HEIGHT - ($xtop + 5), self::LINE_WIDTH, self::PAGE_HEIGHT - ($xtop + 5), [0, 0, 0], 0.5);
        $xtop -= 10;
        $this->placeRight($this->bold, self::FONT_SIZE, self::LEFT + 400, $xtop, 'Collection Grand Total');
        $this->placeRight($this->bold, self::FONT_SIZE, self::LEFT + 580, $xtop, number_format((float) $payload['grand'], 2));

        if ($payload['show_expenses']) {
            $xtop -= 10;
            $firstExp = true;
            foreach ($payload['expenses'] as $expense) {
                if ($xtop <= 50) {
                    $this->canvas->new_page();
                    $this->drawHeader();
                    $xtop = 810.0;
                }
                if ($firstExp) {
                    $this->placeText($this->bold, self::FONT_SIZE, self::LEFT, $xtop, 'Less Expenses : ');
                    $xtop -= 10;
                    $firstExp = false;
                }
                $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop, $expense['trndte']);
                $this->placeText($this->font, self::FONT_SIZE, self::LEFT + $this->widths[1], $xtop, $expense['itmdsc']);
                $this->placeRight($this->font, self::FONT_SIZE, self::LEFT + $this->widths[5] + 30, $xtop, number_format((float) $expense['amount'], 2));
                $xtop -= 12;
            }
            $this->canvas->line(self::LEFT + 500, self::PAGE_HEIGHT - ($xtop - 15), self::LINE_WIDTH, self::PAGE_HEIGHT - ($xtop - 15), [0, 0, 0], 0.5);
            $xtop -= 10;
            $this->placeRight($this->bold, self::FONT_SIZE, self::LEFT + 400, $xtop, 'Expenses Grand Total >>');
            $this->placeRight($this->bold, self::FONT_SIZE, self::LEFT + 580, $xtop, number_format((float) $payload['expense_total'], 2));
            $xtop -= 12;
            $this->placeRight($this->bold, self::FONT_SIZE, self::LEFT + 580, $xtop, number_format((float) $payload['net'], 2));
            $xtop -= 12;
            $this->canvas->line(self::LEFT + 500, self::PAGE_HEIGHT - $xtop, self::LINE_WIDTH, self::PAGE_HEIGHT - $xtop, [0, 0, 0], 0.5);
            $xtop -= 3;
            $this->canvas->line(self::LEFT + 500, self::PAGE_HEIGHT - $xtop, self::LINE_WIDTH, self::PAGE_HEIGHT - $xtop, [0, 0, 0], 0.5);
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
        $out = $payload['company'].$eol.$payload['title'].$eol;
        $out .= 'Period Covered : '.$payload['period'].$eol.'Shift : '.$payload['shift'].$eol;
        $out .= 'Date Generated : '.$payload['printed_at'].$eol.$eol;
        $out .= implode($tab, $payload['headers']).$eol;
        foreach ($payload['groups'] as $group) {
            $first = true;
            foreach ($group['lines'] as $line) {
                $out .= ($first ? $group['header'] : '').$tab;
                $out .= "'".$line['ornum'].$tab.$line['voynum'].$tab.$line['bolnum'].$tab.$line['col5'].$tab;
                $out .= number_format((float) $line['amount'], 2).$eol;
                if ($line['tax'] !== null) {
                    $out .= $tab.$tab.$tab.$tab.'Less w/ tax'.$tab.$tab.number_format((float) $line['tax'], 2).$eol;
                }
                $first = false;
            }
            $out .= $tab.$tab.$tab.$tab.$group['remark'].$tab.$tab.number_format((float) $group['total'], 2).$eol;
        }
        $out .= $tab.$tab.$tab.$tab.'Collection Grand Total'.$tab.$tab.number_format((float) $payload['grand'], 2).$eol;
        if ($payload['show_expenses']) {
            $out .= 'Less Expenses : '.$eol;
            foreach ($payload['expenses'] as $expense) {
                $out .= $expense['trndte'].$tab.$expense['itmdsc'].$tab.$tab.$tab.$tab.number_format((float) $expense['amount'], 2).$eol;
            }
            $out .= $tab.$tab.$tab.$tab.'Expenses Grand Total >>'.$tab.$tab.number_format((float) $payload['expense_total'], 2).$eol;
            $out .= $tab.$tab.$tab.$tab.$tab.$tab.number_format((float) $payload['net'], 2).$eol;
        }

        return $out;
    }

    private function drawHeader(): void
    {
        $xtop = 890.0;
        $this->placeText($this->bold, self::FONT_SIZE + 2, self::LEFT, $xtop, (string) $this->payload['company']);
        $xtop -= 12;
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop, (string) $this->payload['title']);
        $xtop -= 12;
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop, 'Period Covered : '.$this->payload['period']);
        $xtop -= 12;
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop, 'Shift : '.$this->payload['shift']);
        $xtop -= 5;
        $this->canvas->line(self::LEFT, self::PAGE_HEIGHT - $xtop, self::LINE_WIDTH, self::PAGE_HEIGHT - $xtop, [0, 0, 0], 0.5);
        $xtop -= 10;
        foreach ($this->payload['headers'] as $i => $label) {
            $this->placeText($this->bold, self::FONT_SIZE - 1, self::LEFT + ($this->widths[$i] ?? 0), $xtop, $label);
        }
        $xtop -= 4;
        $this->canvas->line(self::LEFT, self::PAGE_HEIGHT - $xtop, self::LINE_WIDTH, self::PAGE_HEIGHT - $xtop, [0, 0, 0], 0.5);
        if (! $this->paged) {
            $this->paged = true;
            $baseline = $this->canvas->get_font_baseline($this->font, 8);
            $this->canvas->page_text(self::LEFT + 10, self::PAGE_HEIGHT - 8 - $baseline, 'Date Printed : '.$this->payload['printed_at'], $this->font, 8);
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
