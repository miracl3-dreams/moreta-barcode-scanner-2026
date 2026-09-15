<?php

namespace App\Support;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;

class ClearingManifestPdf
{
    private const PAGE_HEIGHT = 612.0;

    private const FONT_SIZE = 8.0;

    /** @var list<int> */
    private const POS = [15, 30, 90, 190, 240, 290, 390, 450, 530, 570, 610, 650, 690, 740];

    public function __construct(
        private Canvas $canvas,
        private FontMetrics $metrics,
        private string $font,
        private string $bold,
        private Dompdf $dompdf,
        private string $company = '',
        private array $voyage = [],
        private string $printedAt = '',
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
     * @param  array<string, mixed>  $payload
     */
    public function render(array $payload, bool $fullText): string
    {
        $this->company = (string) ($payload['company'] ?? '');
        $this->voyage = $payload['voyage'];
        $this->printedAt = (string) ($payload['printed_at'] ?? '');

        $this->drawHeader();
        $xtop = 500.0;
        $bodyTop = $xtop;
        $size = self::FONT_SIZE - 1;
        $totCbm = 0.0;
        $totMt = 0.0;
        $totValue = 0.0;
        $count = 1;

        foreach ($payload['bills'] as $bill) {
            $this->placeText($this->font, $size, (float) self::POS[0], $xtop, (string) $count);
            $this->placeText($this->font, $size, (float) self::POS[4], $xtop, $bill['docnum']);
            $shipper = $fullText ? $bill['cusdsc'] : substr($bill['cusdsc'], 0, 10);
            $consignee = $fullText ? $bill['concde'] : substr($bill['concde'], 0, 15);
            $this->placeText($this->font, $size, (float) self::POS[6], $xtop, $shipper);
            $this->placeText($this->font, $size, (float) self::POS[7], $xtop, $consignee);

            $lineCount = 0;
            foreach ($bill['lines'] as $line) {
                $totCbm += (float) $line['itmqty'];
                $totMt += (float) $line['weiamt'];
                $totValue += (float) $line['value'];
                $vsm = self::vanSealMarks($line, $fullText);
                $cat = $fullText ? $line['catcde'] : substr($line['catcde'], 0, 16);
                $pack = $fullText ? $line['class'] : substr($line['class'], 0, 11);
                $desc = $fullText ? $line['classdsc'] : substr($line['classdsc'], 0, 18);
                $this->placeText($this->font, $size, (float) self::POS[1], $xtop, $cat);
                $this->placeText($this->font, $size, (float) self::POS[2], $xtop, $fullText ? $vsm : substr($vsm, 0, 20));
                $this->placeText($this->font, $size, (float) self::POS[3], $xtop, $pack);
                $this->placeText($this->font, $size, (float) self::POS[5], $xtop, $desc);
                $this->placeRight($this->font, $size, (float) self::POS[8] + 20, $xtop, self::formatQty($line['itmqty']));
                $this->placeRight($this->font, $size, (float) self::POS[9] + 10, $xtop, self::formatQty($line['weiamt']));
                $this->placeRight($this->font, $size, (float) self::POS[10] + 30, $xtop, self::formatMoney($line['value']));
                $xtop -= 8;
                if (! $fullText && $xtop <= 20) {
                    $this->canvas->new_page();
                    $this->drawHeader();
                    $xtop = $bodyTop;
                }
                $lineCount++;
            }
            if ($lineCount === 0) {
                $xtop -= 8;
            }
            if (! $fullText && $xtop <= 20) {
                $this->canvas->new_page();
                $this->drawHeader();
                $xtop = $bodyTop;
            }
            $count++;
        }

        $xtop += 7;
        $this->line(10, $xtop, 790);
        $xtop -= 10;
        $this->placeRight($this->bold, $size, (float) self::POS[8] + 20, $xtop, self::formatQty($totCbm));
        $this->placeRight($this->bold, $size, (float) self::POS[9] + 10, $xtop, self::formatQty($totMt));
        $this->placeRight($this->bold, $size, (float) self::POS[10] + 30, $xtop, self::formatMoney($totValue));
        if (! $fullText && $xtop <= 180) {
            $this->canvas->new_page();
            $this->drawHeader();
            $xtop = $bodyTop;
        }
        $this->drawRecap($xtop, $fullText);

        return $this->dompdf->output(['compress' => 1]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function exportText(array $payload): string
    {
        $tab = "\t";
        $eol = "\r\n";
        $v = $payload['voyage'];
        $chunk = strtoupper((string) $payload['company']).$tab.$eol
            .'COASTING MANIFEST '.$tab.$eol
            .'VESSEL NAME : '.substr((string) $v['vsslcde'], 0, 14).'.'.$tab
            .'VOYAGE NO. : '.$v['voynum'].$tab
            .'PORT OF ORIGIN : '.$v['origin'].$tab
            .'PORT OF DESTINATION : '.substr((string) $v['dstcde'], 0, 15).$tab
            .'ETA : '.$v['eta'].$tab
            .'ETD : '.$v['sailing'].$tab.$eol
            .'Date Printed : '.$payload['printed_at'].$tab.$eol
            .$tab.'Unit'.$tab.'Van # /Seal # /Marks'.$tab.'Packaging'.$tab.'B/L #'.$tab
            .'Cargo Description &'.$tab.'Shipper &'.$tab.'Consignee &'.$tab.'CBM'.$tab.'MT'.$tab.'Value'.$tab
            .'Freight'.$tab.'Freight'.$tab.'With'.$tab.$eol
            .$tab."(10'/20'/40')".$tab.$tab.$tab.$tab.'Commodity'.$tab.'Address'.$tab.'Address'.$tab.$tab.$tab.$tab
            .'Collect'.$tab.'Prepaid'.$tab.'Revolving'.$tab.$eol
            .$tab.$tab.$tab.$tab.$tab.'Classification'.$tab.$tab.$tab.$tab.$tab.$tab.$tab.$tab.'Fund'.$tab.$eol;

        $totCbm = 0.0;
        $totMt = 0.0;
        $totValue = 0.0;
        $count = 1;
        foreach ($payload['bills'] as $bill) {
            $first = true;
            foreach ($bill['lines'] as $line) {
                $totCbm += (float) $line['itmqty'];
                $totMt += (float) $line['weiamt'];
                $totValue += (float) $line['value'];
                $chunk .= ($first ? (string) $count : '').$tab
                    .$line['catcde'].$tab
                    .self::vanSealMarks($line, true).$tab
                    .$line['class'].$tab
                    .($first ? $bill['docnum'] : '').$tab
                    .$line['classdsc'].$tab
                    .($first ? $bill['cusdsc'] : '').$tab
                    .($first ? $bill['concde'] : '').$tab
                    .self::formatQty($line['itmqty']).$tab
                    .self::formatQty($line['weiamt']).$tab
                    .self::formatMoney($line['value']).$tab.$eol;
                $first = false;
            }
            if ($first) {
                $chunk .= $count.$tab.$tab.$tab.$tab.$bill['docnum'].$tab.$tab.$bill['cusdsc'].$tab.$bill['concde'].$tab.$eol;
            }
            $count++;
        }
        $chunk .= $tab.$tab.$tab.$tab.$tab.$tab.$tab.$tab
            .self::formatQty($totCbm).$tab
            .self::formatQty($totMt).$tab
            .self::formatMoney($totValue).$tab.$eol
            .'Recapitulation:'.$tab.$eol
            .'Containerized'.$tab.'Break Bulk'.$tab.'RORO Vehicles'.$tab.$eol
            .'Size'.$tab.'Qty'.$tab.'MT'.$tab.'CBM'.$tab.'Type'.$tab.'Qty'.$tab.$eol
            ."10'".$tab.$tab.$tab.$tab.'1'.$tab.$eol
            ."20'".$tab.$tab.$tab.$tab.'2'.$tab.$eol
            ."40'".$tab.$tab.$tab.$tab.'3'.$tab.$eol
            ."45'".$tab.$tab.$tab.$tab.'4'.$tab.$eol
            .'Empty'.$tab.$eol
            .'_______________________'.$tab.$eol
            .'Name & Signature'.$tab.$eol
            .'Master of the Vessel'.$tab.$eol;

        return $chunk;
    }

    private function drawHeader(): void
    {
        $xtop = 590.0;
        $this->placeText($this->bold, self::FONT_SIZE + 2, 320, $xtop, strtoupper($this->company));
        $xtop -= 12;
        $this->placeText($this->font, self::FONT_SIZE, 350, $xtop, 'COASTING MANIFEST ');
        $xtop -= 20;
        $this->placeText($this->font, self::FONT_SIZE, 20, $xtop, 'VESSEL NAME : '.substr((string) $this->voyage['vsslcde'], 0, 14).'.');
        $this->placeText($this->font, self::FONT_SIZE, 160, $xtop, 'VOYAGE NO. : '.$this->voyage['voynum']);
        $this->placeText($this->font, self::FONT_SIZE, 280, $xtop, 'PORT OF ORIGIN : '.$this->voyage['origin']);
        $this->placeText($this->font, self::FONT_SIZE, 430, $xtop, 'PORT OF DESTINATION : '.substr((string) $this->voyage['dstcde'], 0, 15));
        $this->placeText($this->font, self::FONT_SIZE, 610, $xtop, 'ETA : '.$this->voyage['eta']);
        $this->placeText($this->font, self::FONT_SIZE, 690, $xtop, 'ETD : '.$this->voyage['sailing']);
        $xtop -= 25;
        $barH = self::FONT_SIZE + 5;
        $this->canvas->filled_rectangle(10, self::PAGE_HEIGHT - $xtop - $barH, 780, $barH, [0.8, 0.8, 0.8]);
        $xtop -= 12;
        $this->canvas->filled_rectangle(10, self::PAGE_HEIGHT - $xtop - $barH, 780, $barH, [0.8, 0.8, 0.8]);
        $xtop -= 12;
        $this->canvas->filled_rectangle(10, self::PAGE_HEIGHT - $xtop - $barH, 780, $barH, [0.8, 0.8, 0.8]);
        $this->canvas->line(10, self::PAGE_HEIGHT - $xtop, 800, self::PAGE_HEIGHT - $xtop, [0, 0, 0], 0.5);
        $xtop += 28;
        $header = ['', '    Unit', 'Van # /Seal # /Marks', 'Packaging', '   B/L #', 'Cargo Description &', 'Shipper &', 'Consignee &', 'CBM', 'MT', 'Value', 'Freight', 'Freight', 'With'];
        $header2 = ['', "(10'/20'/40')", '', '', '', 'Commodity', 'Address', 'Address', '', '', '', 'Collect', 'Prepaid', 'Revolving'];
        $header3 = ['', '', '', '', '', 'Classification', '', '', '', '', '', '', '', 'Fund'];
        foreach ($header as $i => $label) {
            $this->placeText($this->bold, self::FONT_SIZE, (float) self::POS[$i], $xtop, $label);
        }
        $xtop -= 12;
        foreach ($header2 as $i => $label) {
            $this->placeText($this->bold, self::FONT_SIZE, (float) self::POS[$i], $xtop, $label);
        }
        $xtop -= 12;
        foreach ($header3 as $i => $label) {
            $this->placeText($this->bold, self::FONT_SIZE, (float) self::POS[$i], $xtop, $label);
        }
        $this->placeRight($this->font, 8, 775, $xtop + 60, 'Date Printed : '.$this->printedAt);
        $baseline = $this->canvas->get_font_baseline($this->font, 8);
        $this->canvas->page_text(780, self::PAGE_HEIGHT - ($xtop + 90) - $baseline, 'Page | {PAGE_NUM}', $this->font, 8);
    }

    private function drawRecap(float $xtop, bool $fullText): void
    {
        $xtop -= 38;
        $this->canvas->line(40, self::PAGE_HEIGHT - ($xtop + 10), 360, self::PAGE_HEIGHT - ($xtop + 10), [0, 0, 0], 0.5);
        $this->placeText($this->bold, 9, 145, $xtop, 'Recapitulation:');
        $xtop -= 8;
        $this->canvas->line(40, self::PAGE_HEIGHT - $xtop, 360, self::PAGE_HEIGHT - $xtop, [0, 0, 0], 0.5);
        $xtop -= 12;
        $this->placeText($this->font, self::FONT_SIZE, 55, $xtop, 'Containerized');
        $this->placeText($this->font, self::FONT_SIZE, 160, $xtop, 'Break Bulk');
        $this->placeText($this->font, self::FONT_SIZE, 245, $xtop, 'RORO Vehicles');
        $xtop -= 8;
        $this->canvas->line(40, self::PAGE_HEIGHT - $xtop, 360, self::PAGE_HEIGHT - $xtop, [0, 0, 0], 0.5);
        $this->canvas->line(40, self::PAGE_HEIGHT - ($xtop + 38), 95, self::PAGE_HEIGHT - ($xtop - 84), [0, 0, 0], 0.5);
        $this->canvas->line(135, self::PAGE_HEIGHT - ($xtop + 20), 190, self::PAGE_HEIGHT - ($xtop - 84), [0, 0, 0], 0.5);
        $this->canvas->line(225, self::PAGE_HEIGHT - ($xtop + 20), 280, self::PAGE_HEIGHT - ($xtop - 84), [0, 0, 0], 0.5);
        $this->canvas->line(315, self::PAGE_HEIGHT - ($xtop + 38), 370, self::PAGE_HEIGHT - ($xtop - 84), [0, 0, 0], 0.5);
        $this->canvas->line(270, self::PAGE_HEIGHT - $xtop, 325, self::PAGE_HEIGHT - ($xtop - 84), [0, 0, 0], 0.5);
        $this->canvas->line(180, self::PAGE_HEIGHT - $xtop, 235, self::PAGE_HEIGHT - ($xtop - 84), [0, 0, 0], 0.5);
        $this->canvas->line(85, self::PAGE_HEIGHT - $xtop, 140, self::PAGE_HEIGHT - ($xtop - 84), [0, 0, 0], 0.5);
        $xtop -= 12;
        $this->placeText($this->font, self::FONT_SIZE, 54, $xtop, 'Size');
        $this->placeText($this->font, self::FONT_SIZE, 105, $xtop, 'Qty');
        $this->placeText($this->font, self::FONT_SIZE, 145, $xtop, 'MT');
        $this->placeText($this->font, self::FONT_SIZE, 195, $xtop, 'CBM');
        $this->placeText($this->font, self::FONT_SIZE, 237, $xtop, 'Type');
        $this->placeText($this->font, self::FONT_SIZE, 290, $xtop, 'Qty');
        $xtop -= 5;
        $this->canvas->line(40, self::PAGE_HEIGHT - $xtop, 360, self::PAGE_HEIGHT - $xtop, [0, 0, 0], 0.5);
        foreach ([["10'", '1'], ["20'", '2'], ["40'", '3'], ["45'", '4']] as $row) {
            $xtop -= 8;
            $this->placeText($this->font, self::FONT_SIZE, 55, $xtop, $row[0]);
            $this->placeText($this->font, self::FONT_SIZE, 245, $xtop, $row[1]);
            $xtop -= 5;
            $this->canvas->line(40, self::PAGE_HEIGHT - $xtop, 360, self::PAGE_HEIGHT - $xtop, [0, 0, 0], 0.5);
        }
        $xtop -= 10;
        $this->placeText($this->font, self::FONT_SIZE, 50, $xtop, 'Empty');
        $xtop -= 5;
        $this->canvas->line(40, self::PAGE_HEIGHT - $xtop, 360, self::PAGE_HEIGHT - $xtop, [0, 0, 0], 0.5);
        $this->canvas->line(495, self::PAGE_HEIGHT - $xtop, 537, self::PAGE_HEIGHT - $xtop, [0, 0, 0], 0.5);
        if ($fullText) {
            $xtop -= 12;
            $this->placeText($this->font, 9, 350, $xtop, '_______________________');
        }
        $xtop -= 12;
        $this->placeText($this->font, 9, 350, $xtop, 'Name & Signature');
        $xtop -= 12;
        $this->placeText($this->font, 9, 345, $xtop, 'Master of the Vessel');
    }

    /**
     * @param  array{vannum: string, sealnum: string, marks: string}  $line
     */
    private static function vanSealMarks(array $line, bool $full): string
    {
        $parts = [];
        foreach (['vannum', 'sealnum', 'marks'] as $field) {
            $value = trim((string) ($line[$field] ?? ''));
            if ($value === '') {
                continue;
            }
            $parts[] = $full ? $value : substr($value, 0, 11);
        }

        return implode(' / ', $parts);
    }

    private static function formatQty(mixed $amount): string
    {
        $number = (float) $amount;
        if ($amount === null || $number == 0.0) {
            return '';
        }

        return number_format($number);
    }

    private static function formatMoney(mixed $amount): string
    {
        $number = (float) $amount;
        if ($amount === null || $number == 0.0) {
            return '';
        }

        return number_format($number, 2);
    }

    private function line(float $x, float $ezY, float $width): void
    {
        $y = self::PAGE_HEIGHT - $ezY;
        $this->canvas->line($x, $y, $x + $width, $y, [0, 0, 0], 0.5);
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
