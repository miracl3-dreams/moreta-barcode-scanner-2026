<?php

namespace App\Support;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;

class ManifestCargoPdf
{
    public function __construct(
        private Canvas $canvas,
        private FontMetrics $metrics,
        private string $font,
        private string $bold,
        private Dompdf $dompdf,
        private float $pageWidth,
        private float $pageHeight,
        private float $fontSize,
        private string $copy,
        private array $company = [],
        private array $voyage = [],
        private string $printedAt = '',
        /** @var list<string> */
        private array $groups = [],
        /** @var array<string, float> */
        private array $groupX = [],
        private float $totalX = 0.0,
        private float $orX = 0.0,
    ) {}

    public static function checker(): self
    {
        return self::make(1008.0, 792.0, 9.0, 'checker');
    }

    public static function office(): self
    {
        return self::make(1008.0, 1092.0, 10.0, 'office');
    }

    private static function make(float $width, float $height, float $fontSize, string $copy): self
    {
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

        return new self($dompdf->getCanvas(), $fonts, $font, $bold, $dompdf, $width, $height, $fontSize, $copy);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function render(array $payload): string
    {
        $this->company = $payload['company'];
        $this->voyage = $payload['voyage'];
        $this->printedAt = $payload['printed_at'];
        $this->groups = $payload['groups'];
        $this->layoutCharges();

        $this->drawHeader();
        $xtop = $this->copy === 'checker' ? 660.0 : 970.0;
        $bodyTop = $xtop;
        $chargeTotals = [];
        $grand = 0.0;

        foreach ($payload['bills'] as $bill) {
            $grand += (float) $bill['trntot'];
            foreach ($this->groups as $group) {
                $chargeTotals[$group] = ($chargeTotals[$group] ?? 0) + (float) ($bill['charges'][$group] ?? 0);
            }

            if ($this->copy === 'checker') {
                $xtop = $this->drawCheckerBill($bill, $xtop, $bodyTop);
            } else {
                $xtop = $this->drawOfficeBill($bill, $xtop, $bodyTop);
            }
        }

        if ($this->copy === 'checker') {
            $xtop += 10;
            $this->line(5, $xtop, 1010);
            $xtop -= 20;
            $this->placeText($this->bold, $this->fontSize, 10, $xtop, 'Grand Total >>');
            foreach ($this->groups as $group) {
                $this->placeRight($this->font, $this->fontSize, $this->groupX[$group] + 30, $xtop, self::formatInt($chargeTotals[$group] ?? 0));
            }
            $this->placeRight($this->font, $this->fontSize, $this->totalX, $xtop, self::formatInt($grand));
        } else {
            $this->line(10, $xtop, $this->pageWidth - 20);
            $xtop -= 15;
            $this->placeText($this->bold, $this->fontSize + 1, 15, $xtop, 'Grand Total >>');
        }

        return $this->dompdf->output(['compress' => 1]);
    }

    private function layoutCharges(): void
    {
        if ($this->copy === 'checker') {
            $left = 540.0;
            foreach ($this->groups as $group) {
                $this->groupX[$group] = $left;
                $left += 54;
            }
            $this->totalX = $left + 25;
            $this->orX = 920.0;

            return;
        }

        $left = 15.0;
        foreach ($this->groups as $group) {
            $this->groupX[$group] = $left;
            $left += 100;
        }
        $this->totalX = $left + 25;
        $this->orX = $left + 70;
    }

    private function drawHeader(): void
    {
        $name = strtoupper((string) ($this->company['name'] ?? ''));
        if ($this->copy === 'checker') {
            $xtop = 770.0;
            $this->placeCenter($this->bold, $this->fontSize + 1, 500, $xtop, $name);
            $xtop -= 12;
            $this->placeCenter($this->font, $this->fontSize, 500, $xtop, (string) ($this->company['add1'] ?? ''));
            $xtop -= 12;
            $this->placeCenter($this->font, $this->fontSize, 500, $xtop, (string) ($this->company['add2'] ?? ''));
            $xtop -= 12;
            $this->placeCenter($this->font, $this->fontSize, 500, $xtop, 'MANIFEST OF CARGO');
            $xtop -= 18;
            $this->placeText($this->font, $this->fontSize, 5, $xtop, 'M.S. : '.$this->voyage['vsslcde']);
            $this->placeText($this->font, $this->fontSize, 205, $xtop, 'Voyage # : '.$this->voyage['voynum']);
            $this->placeText($this->font, $this->fontSize, 405, $xtop, 'Sailing Date : '.$this->voyage['sailing']);
            $this->placeText($this->font, $this->fontSize, 605, $xtop, 'From : '.$this->voyage['origin'].' To '.$this->voyage['dstcde']);
            $xtop -= 35;
            $this->bar(5 - 9, $xtop, 1010, $this->fontSize + 15, [0.8, 0.8, 0.8]);
            $xtop += 5;
            $headers = [1 => 'Marks', 2 => 'Qty', 3 => 'Cargo Cat.', 5 => 'Van #', 6 => 'Seal #', 7 => 'Msrmnt', 8 => 'Weight', 9 => 'Value'];
            $pos = [1 => 10, 2 => 70, 3 => 115, 5 => 220, 6 => 295, 7 => 345, 8 => 400, 9 => 480];
            foreach ($headers as $key => $label) {
                $this->placeText($this->bold, $this->fontSize, (float) $pos[$key], $xtop, $label);
            }
            foreach ($this->groups as $group) {
                $this->placeText($this->bold, $this->fontSize, $this->groupX[$group], $xtop, strtoupper($group));
            }
            $this->placeText($this->bold, $this->fontSize, $this->totalX - 25, $xtop, 'Total');
            $this->placeText($this->font, $this->fontSize, 25, 20, 'Date Printed : '.$this->printedAt);
            $baseline = $this->canvas->get_font_baseline($this->font, 8);
            $this->canvas->page_text(770, $this->pageHeight - 20 - $baseline, 'Page {PAGE_NUM} of {PAGE_COUNT}', $this->font, 8);

            return;
        }

        $xtop = 1070.0;
        $this->placeCenter($this->bold, $this->fontSize + 1, 850, $xtop, $name);
        $xtop -= 12;
        $this->placeCenter($this->font, $this->fontSize, 850, $xtop, (string) ($this->company['add1'] ?? ''));
        $xtop -= 12;
        $this->placeCenter($this->font, $this->fontSize, 850, $xtop, (string) ($this->company['add2'] ?? ''));
        $xtop -= 12;
        $this->placeCenter($this->font, $this->fontSize, 850, $xtop, 'MANIFEST OF CARGO');
        $xtop -= 18;
        $this->placeText($this->font, $this->fontSize, 10, $xtop, 'M.S. : '.$this->voyage['vsslcde']);
        $this->placeText($this->font, $this->fontSize, 200, $xtop, 'Voyage # : '.$this->voyage['voynum']);
        $this->placeText($this->font, $this->fontSize, 390, $xtop, 'Sailing Date : '.$this->voyage['sailing']);
        $this->placeText($this->font, $this->fontSize, 580, $xtop, 'From : '.$this->voyage['origin'].' To '.$this->voyage['dstcde']);
        $xtop -= 35;
        $this->bar(0, $xtop, $this->pageWidth, $this->fontSize + 15, [0.9, 0.9, 0.9]);
        $xtop = 990.0;
        $headers = [15 => 'BL#', 130 => 'Qty', 180 => 'Cargo Cat.', 310 => 'Shipper Code', 500 => 'Consignee Code', 700 => 'Van#', 780 => 'Marks', 850 => 'Description'];
        foreach ($headers as $x => $label) {
            $this->placeText($this->bold, $this->fontSize, (float) $x, $xtop, $label);
        }
        $this->placeText($this->font, $this->fontSize, 10, 20, 'Date Printed : '.$this->printedAt);
    }

    /**
     * @param  array<string, mixed>  $bill
     */
    private function drawCheckerBill(array $bill, float $xtop, float $bodyTop): float
    {
        $this->placeRight($this->bold, $this->fontSize, 920, $xtop, 'OR # : '.$bill['ornum'].'   '.$bill['ordate']);
        foreach ($this->groups as $group) {
            $amount = (float) ($bill['charges'][$group] ?? 0);
            $text = $amount > 0 ? number_format($amount, 2) : self::formatInt($amount);
            $this->placeRight($this->font, $this->fontSize, $this->groupX[$group] + 40, $xtop, $text);
        }
        $this->placeRight($this->font, $this->fontSize, $this->totalX, $xtop, number_format((float) $bill['trntot'], 2));

        foreach ($bill['lines'] as $line) {
            $qty = $line['qty'] === '' ? '' : strtolower($line['qty'].' '.$line['class']);
            $this->placeText($this->font, $this->fontSize, 10, $xtop, substr($line['marks'], 0, 10));
            $this->placeRight($this->font, $this->fontSize, 100, $xtop, $qty);
            $this->placeText($this->font, $this->fontSize, 115, $xtop, substr($line['catcde'], 0, 17));
            $this->placeText($this->font, $this->fontSize, 220, $xtop, substr($line['vannum'], 0, 11));
            $this->placeText($this->font, $this->fontSize - 1, 295, $xtop, substr($line['sealnum'], 0, 10));
            $this->placeRight($this->font, $this->fontSize, 370, $xtop, self::formatInt($line['itmqty']));
            $this->placeRight($this->font, $this->fontSize, 430, $xtop, number_format((float) $line['weiamt'], 2));
            $this->placeRight($this->font, $this->fontSize, 505, $xtop, number_format((float) $line['value'], 2));
            $xtop -= 15;
            $this->placeText($this->bold, $this->fontSize + 1, 10, $xtop, 'BL# : '.$bill['docnum']);
            $xtop -= 12;
            $this->placeText($this->bold, $this->fontSize, 10, $xtop, 'Shipper Code : '.substr($bill['cuscde'], 0, 20));
            $xtop -= 12;
            $this->placeText($this->bold, $this->fontSize, 10, $xtop, 'Consignee : '.substr($bill['condsc'], 0, 19));
            $xtop -= 12;
            $this->placeText($this->bold, $this->fontSize, 10, $xtop, 'Description : '.$line['classdsc']);
            $xtop -= 12;
            $this->placeText($this->bold, $this->fontSize, 10, $xtop, 'Mode of Shipment: '.$bill['shipmode']);
            $xtop -= 12;
            if ($xtop <= 100) {
                $this->canvas->new_page();
                $this->drawHeader();
                $xtop = $bodyTop;
            }
        }

        $xtop -= 12;
        if ($xtop <= 35) {
            $this->canvas->new_page();
            $this->drawHeader();
            $xtop = $bodyTop;
        }

        return $xtop;
    }

    /**
     * @param  array<string, mixed>  $bill
     */
    private function drawOfficeBill(array $bill, float $xtop, float $bodyTop): float
    {
        $this->placeText($this->bold, $this->fontSize, 15, $xtop, $bill['docnum']);
        $this->placeText($this->bold, $this->fontSize, 310, $xtop, substr($bill['cuscde'], 0, 25));
        $this->placeText($this->bold, $this->fontSize, 500, $xtop, substr($bill['condsc'], 0, 25));

        foreach ($bill['lines'] as $line) {
            $qty = $line['qty'] === '' ? '' : $line['qty'].' '.$line['class'];
            $this->placeCenter($this->font, $this->fontSize, 130, $xtop, $qty);
            $this->placeText($this->font, $this->fontSize, 180, $xtop, substr($line['catcde'], 0, 23));
            $this->placeText($this->font, $this->fontSize, 700, $xtop, substr($line['vannum'], 0, 11));
            $this->placeText($this->font, $this->fontSize, 780, $xtop, substr($line['marks'], 0, 10));
            $this->placeText($this->font, $this->fontSize - 1, 850, $xtop, substr($line['classdsc'], 0, 36));
            $xtop -= 12;
            if ($xtop < 100) {
                $this->canvas->new_page();
                $this->drawHeader();
                $xtop = $bodyTop;
            }
        }

        return $xtop;
    }

    private function bar(float $x, float $ezY, float $width, float $height, array $color): void
    {
        $this->canvas->filled_rectangle($x, $this->pageHeight - $ezY - $height, $width, $height, $color);
        $lineY = $this->pageHeight - $ezY;
        $this->canvas->line($x, $lineY, $x + $width, $lineY, [0, 0, 0], 0.5);
    }

    private function line(float $x, float $ezY, float $width): void
    {
        $y = $this->pageHeight - $ezY;
        $this->canvas->line($x, $y, $x + $width, $y, [0, 0, 0], 0.5);
    }

    private function placeCenter(string $font, float $size, float $center, float $ezY, string $text): void
    {
        $text = self::pdfText($text);
        if ($text === '') {
            return;
        }
        $width = $this->metrics->getTextWidth($text, $font, $size);
        $this->placeText($font, $size, $center - ($width / 2), $ezY, $text);
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
        $canvasY = $this->pageHeight - $ezY - $this->canvas->get_font_baseline($font, $size);
        $this->canvas->text($x, $canvasY, self::pdfText($text), $font, $size);
    }

    public static function formatInt(mixed $amount): string
    {
        $number = (float) $amount;
        if ($amount === '' || $amount === null || $number <= 0) {
            return '';
        }

        return number_format($number);
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
