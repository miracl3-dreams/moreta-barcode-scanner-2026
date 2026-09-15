<?php

namespace App\Support;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;

class StatementAccountPdf
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
        private float $left,
        private float $lineWidth,
        private float $centerX,
        private float $headerTop,
        private float $bodyTop,
        private bool $paged = false,
    ) {}

    public static function folioLandscape(): self
    {
        return self::make(936.0, 612.0, 10.0, 25.0, 890.0, 475.0, 575.0, 425.0);
    }

    public static function letterLandscape(): self
    {
        return self::make(792.0, 612.0, 8.0, 20.0, 755.0, 400.0, 600.0, 470.0);
    }

    private static function make(
        float $width,
        float $height,
        float $fontSize,
        float $left,
        float $lineWidth,
        float $centerX,
        float $headerTop,
        float $bodyTop,
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
            $width,
            $height,
            $fontSize,
            $left,
            $lineWidth,
            $centerX,
            $headerTop,
            $bodyTop,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function byShipperConsignee(array $payload): string
    {
        $headers = [
            30 => 'Date of Voyage',
            150 => 'Voyage No.',
            260 => 'B.L No.',
            400 => 'Freight',
            500 => 'Partial Payment',
            600 => 'OR No.',
            720 => 'Outstanding Balance',
            840 => 'Days Overdue',
        ];
        $this->drawStatementHeader($payload, $headers, true);
        $xtop = $this->bodyTop;
        $freightTotal = 0.0;
        $partialTotal = 0.0;
        $grandTotal = 0.0;
        foreach ($payload['rows'] as $row) {
            $this->placeText($this->font, $this->fontSize, 30, $xtop, $row['trndte']);
            $this->placeText($this->font, $this->fontSize, 150, $xtop, $row['voynum']);
            $this->placeText($this->font, $this->fontSize, 260, $xtop, $row['docnum']);
            $this->placeRight($this->font, $this->fontSize, 430, $xtop, self::money($row['freight']));
            $this->placeRight($this->font, $this->fontSize, 800, $xtop, self::money($row['balance']));
            $this->placeRight($this->font, $this->fontSize, 870, $xtop, (string) $row['duedays']);
            $receipts = $row['receipts'];
            foreach ($receipts as $index => $receipt) {
                $this->placeRight($this->font, $this->fontSize, 560, $xtop, self::money($receipt['amtapp']));
                $this->placeText($this->font, $this->fontSize, 600, $xtop, $receipt['docnum']);
                $partialTotal += $receipt['amtapp'];
                if ($index < count($receipts) - 1) {
                    $xtop -= 10;
                }
            }
            $freightTotal += $row['freight'];
            $grandTotal += $row['balance'];
            $xtop -= 15;
            if ($xtop < 50) {
                $this->canvas->new_page();
                $this->drawStatementHeader($payload, $headers, true);
                $xtop = $this->bodyTop;
            }
        }
        $this->lineAt($xtop + 11);
        $this->placeText($this->bold, $this->fontSize, 30, $xtop, 'GRAND TOTAL');
        $this->placeRight($this->font, $this->fontSize, 430, $xtop, self::money($freightTotal));
        $this->placeRight($this->font, $this->fontSize, 560, $xtop, self::money($partialTotal));
        $this->placeRight($this->font, $this->fontSize, 800, $xtop, self::money($grandTotal));

        return $this->dompdf->output(['compress' => 1]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function byVoyage(array $payload): string
    {
        $this->fontSize = 8.0;
        $headers = [
            22 => 'Date of Voyage',
            80 => 'Voyage No.',
            150 => 'BL #',
            220 => 'Consignee',
            335 => 'Shipper',
            450 => 'Total Freight',
            510 => 'Amount Paid',
            560 => 'OR Number',
            630 => 'Outstanding Balance',
            720 => 'Days Overdue',
        ];
        $this->drawStatementHeader($payload, $headers, false, $payload['boldte'] ?? '');
        $xtop = $this->bodyTop;
        $freightTotal = 0.0;
        $paidTotal = 0.0;
        $grandTotal = 0.0;
        foreach ($payload['rows'] as $row) {
            $this->placeText($this->font, $this->fontSize, 22, $xtop, $row['trndte']);
            $this->placeText($this->font, $this->fontSize, 80, $xtop, $row['voynum']);
            $this->placeText($this->font, $this->fontSize, 150, $xtop, $row['docnum']);
            $this->placeText($this->font, $this->fontSize, 220, $xtop, substr($row['concde'], 0, 23));
            $this->placeText($this->font, $this->fontSize, 335, $xtop, substr($row['cuscde'], 0, 23));
            $this->placeRight($this->font, $this->fontSize, 480, $xtop, self::money($row['freight']));
            $receipts = $row['receipts'];
            $pass = false;
            foreach ($receipts as $index => $receipt) {
                $this->placeRight($this->font, $this->fontSize, 550, $xtop, self::money($receipt['amtapp']));
                $this->placeText($this->font, $this->fontSize, 560, $xtop, $receipt['docnum']);
                $paidTotal += $receipt['amtapp'];
                if ($index < count($receipts) - 1) {
                    $pass = true;
                    $xtop -= 15;
                }
            }
            $adj = $pass ? 15.0 : 0.0;
            $this->placeRight($this->font, $this->fontSize, 690, $xtop + $adj, self::money($row['balance']));
            $this->placeRight($this->font, $this->fontSize, 760, $xtop + $adj, (string) $row['duedays']);
            $freightTotal += $row['frghtamt'];
            $grandTotal += $row['balance'];
            $xtop -= 15;
            if ($xtop < 50) {
                $this->canvas->new_page();
                $this->drawStatementHeader($payload, $headers, false, $payload['boldte'] ?? '');
                $xtop = $this->bodyTop;
            }
        }
        $this->lineAt($xtop + 11);
        $this->placeText($this->bold, $this->fontSize, 22, $xtop, 'GRANDTOTAL');
        $this->placeRight($this->font, $this->fontSize, 480, $xtop, self::money($freightTotal));
        $this->placeRight($this->font, $this->fontSize, 550, $xtop, self::money($paidTotal));
        $this->placeRight($this->font, $this->fontSize, 690, $xtop, self::money($grandTotal));

        return $this->dompdf->output(['compress' => 1]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function byVoyageNew(array $payload): string
    {
        $this->fontSize = 8.0;
        $headers = [
            22 => 'BL #',
            80 => 'Date of Voyage',
            150 => 'Voyage No.',
            220 => 'Activity Log Date',
            335 => 'Activity Log Date',
            450 => 'Activity Log Remarks',
        ];
        $this->drawStatementHeader($payload, $headers, false, $payload['boldte'] ?? '');
        $xtop = $this->bodyTop;
        foreach ($payload['rows'] as $row) {
            $this->placeText($this->font, $this->fontSize, 22, $xtop, $row['docnum']);
            $this->placeText($this->font, $this->fontSize, 80, $xtop, $row['trndte']);
            $this->placeText($this->font, $this->fontSize, 150, $xtop, $row['voynum']);
            foreach ($row['activities'] as $activity) {
                $this->placeText($this->font, $this->fontSize, 220, $xtop, $activity['date']);
                $this->placeText($this->font, $this->fontSize, 335, $xtop, $activity['time']);
                $lines = $this->wrapText($activity['remarks'], 250.0, $this->fontSize);
                if ($lines === []) {
                    $xtop -= 10;
                }
                foreach ($lines as $line) {
                    $this->placeText($this->font, $this->fontSize, 450, $xtop, $line);
                    $xtop -= 10;
                }
            }
            $xtop -= 15;
            if ($xtop < 50) {
                $this->canvas->new_page();
                $this->drawStatementHeader($payload, $headers, false, $payload['boldte'] ?? '');
                $xtop = $this->bodyTop;
            }
        }

        return $this->dompdf->output(['compress' => 1]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function allOutstanding(array $payload): string
    {
        $this->fontSize = 9.0;
        $headers = [
            30 => 'Date of Voyage',
            90 => (string) $payload['client_header'],
            190 => (string) $payload['other_header'],
            280 => 'Voyage No.',
            340 => 'BL #',
            430 => 'Freight',
            480 => 'Partial Payment',
            560 => 'OR Number',
            640 => 'Outstanding Balance',
            725 => 'Days Overdue',
        ];
        $this->drawStatementHeader($payload, $headers, true);
        $xtop = $this->bodyTop;
        $grandTotal = 0.0;
        foreach ($payload['rows'] as $row) {
            $client = substr((string) $row['client'], 0, 13).'..';
            $other = substr((string) $row['other'], 0, 13).'..';
            $this->placeText($this->font, $this->fontSize, 30, $xtop, $row['trndte']);
            $this->placeText($this->font, $this->fontSize, 90, $xtop, $client);
            $this->placeText($this->font, $this->fontSize, 190, $xtop, $other);
            $this->placeText($this->font, $this->fontSize, 280, $xtop, $row['voynum']);
            $this->placeText($this->font, $this->fontSize, 340, $xtop, $row['docnum']);
            $this->placeRight($this->font, $this->fontSize, 460, $xtop, self::money($row['freight']));
            $receipts = $row['receipts'];
            $pass = false;
            foreach ($receipts as $index => $receipt) {
                $this->placeRight($this->font, $this->fontSize, 535, $xtop, self::money($receipt['amtapp']));
                $this->placeText($this->font, $this->fontSize, 560, $xtop, $receipt['docnum']);
                if ($index < count($receipts) - 1) {
                    $pass = true;
                    $xtop -= 15;
                }
            }
            $adj = $pass ? 15.0 : 0.0;
            $this->placeRight($this->font, $this->fontSize, 700, $xtop + $adj, self::money($row['balance']));
            $this->placeRight($this->font, $this->fontSize, 755, $xtop + $adj, (string) $row['duedays']);
            $grandTotal += $row['balance'];
            $xtop -= 15;
            if ($xtop < 50) {
                $this->canvas->new_page();
                $this->drawStatementHeader($payload, $headers, true);
                $xtop = $this->bodyTop;
            }
        }
        $this->lineAt($xtop + 11);
        $this->placeText($this->bold, $this->fontSize, 30, $xtop, 'GRANDTOTAL');
        $this->placeRight($this->font, $this->fontSize, 700, $xtop, self::money($grandTotal));

        return $this->dompdf->output(['compress' => 1]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function byScExport(array $payload): string
    {
        $tab = "\t";
        $eol = "\r\n";
        $out = strtoupper((string) $payload['company']).$eol;
        $out .= $payload['add1'].$eol.$payload['add2'].$eol;
        $out .= 'STATEMENT OF ACCOUNT'.$eol.$payload['subtitle'].$eol.$payload['period'].$eol;
        $out .= 'Date Printed : '.$payload['printed_at'].$tab.$payload['payee_name'].$eol.$eol;
        $out .= implode($tab, ['Date of Voyage', 'Voyage No.', 'B.L No.', 'Freight', 'Partial Payment', 'OR No.', 'Outstanding Balance', 'Days Overdue']).$eol;
        $freightTotal = 0.0;
        $partialTotal = 0.0;
        $grandTotal = 0.0;
        foreach ($payload['rows'] as $row) {
            $receipts = $row['receipts'] ?: [['amtapp' => '', 'docnum' => '']];
            foreach ($receipts as $index => $receipt) {
                $out .= ($index === 0 ? $row['trndte'] : '').$tab;
                $out .= ($index === 0 ? $row['voynum'] : '').$tab;
                $out .= ($index === 0 ? $row['docnum'] : '').$tab;
                $out .= ($index === 0 ? self::money($row['freight']) : '').$tab;
                $out .= ($receipt['amtapp'] === '' ? '' : self::money((float) $receipt['amtapp'])).$tab;
                $out .= $receipt['docnum'].$tab;
                $out .= ($index === 0 ? self::money($row['balance']) : '').$tab;
                $out .= ($index === 0 ? (string) $row['duedays'] : '').$eol;
                $partialTotal += (float) ($receipt['amtapp'] ?: 0);
            }
            $freightTotal += $row['freight'];
            $grandTotal += $row['balance'];
        }
        $out .= 'GRAND TOTAL'.$tab.$tab.$tab.self::money($freightTotal).$tab.self::money($partialTotal).$tab.$tab.self::money($grandTotal).$eol;

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function byVoyageExport(array $payload): string
    {
        $tab = "\t";
        $eol = "\r\n";
        $out = strtoupper((string) $payload['company']).$eol.$payload['add1'].$eol;
        $out .= 'STATEMENT OF ACCOUNT'.$eol.$payload['subtitle'].$eol.$eol;
        $out .= implode($tab, ['Date of Voyage', 'Voyage No.', 'BL #', 'Consignee', 'Shipper', 'Total Freight', 'Amount Paid', 'OR Number', 'Outstanding Balance', 'Days Overdue']).$eol;
        $freightTotal = 0.0;
        $paidTotal = 0.0;
        $grandTotal = 0.0;
        foreach ($payload['rows'] as $row) {
            $receipts = $row['receipts'] ?: [['amtapp' => '', 'docnum' => '']];
            foreach ($receipts as $index => $receipt) {
                $out .= ($index === 0 ? $row['trndte'] : '').$tab;
                $out .= ($index === 0 ? $row['voynum'] : '').$tab;
                $out .= ($index === 0 ? $row['docnum'] : '').$tab;
                $out .= ($index === 0 ? $row['concde'] : '').$tab;
                $out .= ($index === 0 ? $row['cuscde'] : '').$tab;
                $out .= ($index === 0 ? self::money($row['freight']) : '').$tab;
                $out .= ($receipt['amtapp'] === '' ? '' : self::money((float) $receipt['amtapp'])).$tab;
                $out .= $receipt['docnum'].$tab;
                $out .= ($index === 0 ? self::money($row['balance']) : '').$tab;
                $out .= ($index === 0 ? (string) $row['duedays'] : '').$eol;
                $paidTotal += (float) ($receipt['amtapp'] ?: 0);
            }
            $freightTotal += $row['frghtamt'];
            $grandTotal += $row['balance'];
        }
        $out .= 'GRANDTOTAL'.$tab.$tab.$tab.$tab.$tab.self::money($freightTotal).$tab.self::money($paidTotal).$tab.$tab.self::money($grandTotal).$eol;

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function outstandingExport(array $payload): string
    {
        $tab = "\t";
        $eol = "\r\n";
        $out = strtoupper((string) $payload['company']).$eol.$payload['add1'].$eol.$payload['add2'].$eol;
        $out .= 'STATEMENT OF ACCOUNT'.$eol.$payload['subtitle'].$eol.$eol;
        $out .= implode($tab, [
            'Date of Voyage',
            $payload['client_header'],
            $payload['other_header'],
            'Voyage No.',
            'BL #',
            'Freight',
            'Partial Payment',
            'OR Number',
            'Outstanding Balance',
            'Days Overdue',
        ]).$eol;
        $grandTotal = 0.0;
        foreach ($payload['rows'] as $row) {
            $receipts = $row['receipts'] ?: [['amtapp' => '', 'docnum' => '']];
            foreach ($receipts as $index => $receipt) {
                $out .= ($index === 0 ? $row['trndte'] : '').$tab;
                $out .= ($index === 0 ? $row['client'] : '').$tab;
                $out .= ($index === 0 ? $row['other'] : '').$tab;
                $out .= ($index === 0 ? $row['voynum'] : '').$tab;
                $out .= ($index === 0 ? $row['docnum'] : '').$tab;
                $out .= ($index === 0 ? self::money($row['freight']) : '').$tab;
                $out .= ($receipt['amtapp'] === '' ? '' : self::money((float) $receipt['amtapp'])).$tab;
                $out .= $receipt['docnum'].$tab;
                $out .= ($index === 0 ? self::money($row['balance']) : '').$tab;
                $out .= ($index === 0 ? (string) $row['duedays'] : '').$eol;
            }
            $grandTotal += $row['balance'];
        }
        $out .= 'GRANDTOTAL'.$tab.$tab.$tab.$tab.$tab.$tab.$tab.$tab.self::money($grandTotal).$eol;

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $headers
     */
    private function drawStatementHeader(array $payload, array $headers, bool $withAddress2, string $extra = ''): void
    {
        $xtop = $this->headerTop;
        $this->placeCentered($this->bold, $this->fontSize + 1, $this->centerX, $xtop, strtoupper((string) $payload['company']));
        $xtop -= 12;
        $this->placeCentered($this->font, $this->fontSize, $this->centerX, $xtop, (string) $payload['add1']);
        if ($withAddress2) {
            $xtop -= 12;
            $this->placeCentered($this->font, $this->fontSize, $this->centerX, $xtop, (string) ($payload['add2'] ?? ''));
        }
        $xtop -= 24;
        $this->placeCentered($this->font, $this->fontSize, $this->centerX, $xtop, 'STATEMENT OF ACCOUNT');
        $xtop -= 12;
        $this->placeCentered($this->font, $this->fontSize, $this->centerX, $xtop, (string) ($payload['subtitle'] ?? ''));
        if (trim((string) ($payload['period'] ?? '')) !== '') {
            $xtop -= 15;
            $this->placeCentered($this->font, $this->fontSize, $this->centerX, $xtop, (string) $payload['period']);
        }
        if ($extra !== '') {
            $xtop -= 12;
            $this->placeCentered($this->bold, $this->fontSize, $this->centerX, $xtop, $extra);
        }
        $xtop -= 24;
        if (isset($payload['printed_at']) && isset($payload['payee_name'])) {
            $this->placeText($this->bold, $this->fontSize, $this->left + 680, $xtop, ' Date Printed : '.$payload['printed_at']);
            $this->placeCentered($this->font, $this->fontSize, 150, $xtop, (string) $payload['payee_name']);
        } elseif (isset($payload['printed_at']) && $withAddress2) {
            $this->placeCentered($this->bold, $this->fontSize, $this->centerX, $xtop, (string) $payload['printed_at']);
        }
        $xtop -= 30;
        $bar = $this->fontSize + 5;
        $this->canvas->filled_rectangle(
            $this->left,
            $this->pageHeight - $xtop - $bar,
            $this->lineWidth,
            $bar,
            [0.8, 0.8, 0.8],
        );
        $this->canvas->line(
            $this->left,
            $this->pageHeight - $xtop,
            $this->left + $this->lineWidth,
            $this->pageHeight - $xtop,
            [0, 0, 0],
            0.5,
        );
        foreach ($headers as $x => $label) {
            $this->placeText($this->bold, $this->fontSize, (float) $x, $xtop + 5, $label);
        }
        if (! $this->paged) {
            $this->paged = true;
            $baseline = $this->canvas->get_font_baseline($this->font, 8);
            $this->canvas->page_text(
                $this->left + $this->lineWidth - 10,
                $this->pageHeight - 8 - $baseline,
                'Page {PAGE_NUM} of {PAGE_COUNT}',
                $this->font,
                8,
            );
        }
    }

    /**
     * @return list<string>
     */
    private function wrapText(string $text, float $width, float $size): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $try = $current === '' ? $word : $current.' '.$word;
            if ($this->metrics->getTextWidth($try, $this->font, $size) <= $width) {
                $current = $try;

                continue;
            }
            if ($current !== '') {
                $lines[] = $current;
            }
            $current = $word;
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private function lineAt(float $ezY): void
    {
        $y = $this->pageHeight - $ezY;
        $this->canvas->line($this->left, $y, $this->left + $this->lineWidth, $y, [0, 0, 0], 0.5);
    }

    private function placeCentered(string $font, float $size, float $centerX, float $ezY, string $text): void
    {
        $text = self::pdfText($text);
        if ($text === '') {
            return;
        }
        $width = $this->metrics->getTextWidth($text, $font, $size);
        $this->placeText($font, $size, $centerX - ($width / 2), $ezY, $text);
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
        $canvasY = $this->pageHeight - $ezY - $this->canvas->get_font_baseline($font, $size);
        $this->canvas->text($x, $canvasY, $text, $font, $size);
    }

    private static function money(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }

    private static function pdfText(string $text): string
    {
        return str_replace(["\r", "\n"], ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
