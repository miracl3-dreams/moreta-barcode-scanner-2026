<?php

namespace App\Support;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;

class VanEndorsementPdf
{
    private const PAGE_WIDTH = 612.0;

    private const PAGE_HEIGHT = 792.0;

    private const FONT_SIZE = 9.0;

    private const LEFT = 25.0;

    private const LINE_WIDTH = 570.0;

    private const BODY_TOP = 590.0;

    private const GAP = 24.0;

    public function __construct(
        private Canvas $canvas,
        private FontMetrics $metrics,
        private string $font,
        private string $bold,
        private Dompdf $dompdf,
        private string $company = '',
        private string $add1 = '',
        private string $add2 = '',
        private string $printedAt = '',
        private array $voyage = [],
        /** @var list<array{tag: string, catcde: string, full_x: float, mt_x: float, tag_x: float}> */
        private array $categories = [],
        private float $descX = 0.0,
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
        $dompdf->setPaper('letter', 'portrait');
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
        $this->company = (string) ($payload['company'] ?? '');
        $this->add1 = (string) ($payload['add1'] ?? '');
        $this->add2 = (string) ($payload['add2'] ?? '');
        $this->printedAt = (string) ($payload['printed_at'] ?? '');
        $this->voyage = $payload['voyage'] ?? [];
        $this->layoutCategories($payload['categories'] ?? []);
        $this->drawChrome();

        $rows = $payload['rows'] ?? [];
        $xtop = self::BODY_TOP;
        foreach ($rows as $index => $row) {
            $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop - 10, ($index + 1).'.');
            foreach ($this->categories as $category) {
                $cat = $category['catcde'];
                $full = (string) ($row['full'][$cat] ?? '');
                $mt = (string) ($row['mt'][$cat] ?? '');
                $fullLoc = (string) ($row['full_loc'][$cat] ?? '');
                $mtLoc = (string) ($row['mt_loc'][$cat] ?? '');
                if ($full !== '') {
                    $this->placeText($this->font, self::FONT_SIZE, $category['full_x'], $xtop - 10, $full);
                    if (trim($fullLoc) !== '') {
                        $this->placeText($this->font, self::FONT_SIZE - 2, $category['full_x'], $xtop - 22, substr($fullLoc, 0, 15));
                    }
                }
                if ($mt !== '') {
                    $this->placeText($this->font, self::FONT_SIZE, $category['mt_x'], $xtop - 10, $mt);
                    if (trim($mtLoc) !== '') {
                        $this->placeText($this->font, self::FONT_SIZE - 2, $category['mt_x'], $xtop - 22, substr($mtLoc, 0, 15));
                    }
                }
            }

            $descLines = $this->wrapText((string) ($row['desc'] ?? ''), 140.0, self::FONT_SIZE);
            foreach (array_slice($descLines, 0, 3) as $lineIndex => $line) {
                if ($lineIndex > 0) {
                    $xtop -= self::GAP;
                }
                $this->placeText($this->font, self::FONT_SIZE, $this->descX, $xtop - 10, $line);
            }

            $xtop -= self::GAP;
            if ($xtop < 95) {
                $this->canvas->new_page();
                $this->drawChrome();
                $xtop = self::BODY_TOP;
            }
        }

        return $this->dompdf->output(['compress' => 1]);
    }

    /**
     * @param  list<array{tag: string, catcde: string}>  $categories
     */
    private function layoutCategories(array $categories): void
    {
        $yleft = self::LEFT + 30;
        $laid = [];
        foreach ($categories as $category) {
            $laid[] = [
                'tag' => $category['tag'],
                'catcde' => $category['catcde'],
                'full_x' => $yleft - 10,
                'mt_x' => $yleft + 60,
                'tag_x' => $yleft + 40,
            ];
            $yleft += 150;
        }
        $this->categories = $laid;
        $this->descX = $yleft - 30;
    }

    private function drawChrome(): void
    {
        $xtop = 750.0;
        $this->placeCentered($this->bold, 14, 300, $xtop, strtoupper($this->company));
        $xtop -= 12;
        $this->placeCentered($this->font, self::FONT_SIZE, 300, $xtop, $this->add1);
        $xtop -= 12;
        $this->placeCentered($this->font, self::FONT_SIZE, 300, $xtop, $this->add2);
        $xtop -= 17;
        $this->placeCentered($this->font, self::FONT_SIZE + 2, 300, $xtop, 'Van Endorsement');
        $xtop -= 17;
        $this->placeRight($this->bold, self::FONT_SIZE - 1, self::LEFT + self::LINE_WIDTH, $xtop, 'Date Printed : '.$this->printedAt);

        $xtop -= 20;
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop, 'Endorsement on :');
        $xtop -= 12;
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop, 'Vessel : '.($this->voyage['vsslcde'] ?? ''));
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT + 300, $xtop, 'Date : '.($this->voyage['sailing'] ?? ''));
        $xtop -= 12;
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop, 'Voyage No. : '.($this->voyage['voynum'] ?? ''));
        $xtop -= 12;
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, $xtop, 'Origin : '.($this->voyage['origin'] ?? ''));
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT + 300, $xtop, 'Destination : '.($this->voyage['dstdsc'] ?? ''));

        $xtop -= 40;
        $barHeight = self::FONT_SIZE + 15;
        $this->canvas->filled_rectangle(
            self::LEFT,
            self::PAGE_HEIGHT - $xtop - $barHeight,
            self::LINE_WIDTH,
            $barHeight,
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

        $headerY = $xtop + 14;
        foreach ($this->categories as $category) {
            $this->placeCentered($this->bold, self::FONT_SIZE + 1, $category['tag_x'], $headerY, $category['tag']);
            $this->placeText($this->bold, self::FONT_SIZE, $category['full_x'] + 10, $headerY - 10, 'Full');
            $this->placeText($this->bold, self::FONT_SIZE, $category['mt_x'], $headerY - 10, "MT's");
        }
        $this->placeText($this->bold, self::FONT_SIZE, $this->descX, $headerY - 10, 'Description');

        $this->placeText($this->font, self::FONT_SIZE, self::LEFT, 70, 'Endorsed To : ');
        $this->placeText($this->font, self::FONT_SIZE, self::LEFT + 400, 70, 'Endorsed By : ');
        $this->placeCentered($this->font, self::FONT_SIZE, self::LEFT + 100, 42, '________________________________');
        $this->placeCentered($this->font, self::FONT_SIZE, self::LEFT + 450, 42, '_______________________________');
        $this->placeCentered($this->font, self::FONT_SIZE + 1, self::LEFT + 100, 30, 'Signature over printed name');
        $this->placeCentered($this->font, self::FONT_SIZE + 1, self::LEFT + 450, 30, 'Signature over printed name');
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
        $canvasY = self::PAGE_HEIGHT - $ezY - $this->canvas->get_font_baseline($font, $size);
        $this->canvas->text($x, $canvasY, $text, $font, $size);
    }

    private static function pdfText(string $text): string
    {
        return str_replace(["\r", "\n"], ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
