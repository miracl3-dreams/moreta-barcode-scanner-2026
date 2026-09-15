<?php

namespace App\Services;

use App\Repositories\VanInventoryRepository;
use App\Support\VanInventoryPdf;
use Illuminate\Http\Response;

class VanInventoryService
{
    /**
     * @var list<string>
     */
    private const DATE_FIELDS = [
        'acqdte',
        'paydte',
        'start_rentaldate',
        'end_rentaldate',
        'date_fabricated',
        'date_sold',
        'date_scrapped',
    ];

    public function __construct(private VanInventoryRepository $inventory) {}

    /**
     * @return array{types: list<string>}
     */
    public function lookups(): array
    {
        return [
            'types' => $this->inventory->types(),
        ];
    }

    /**
     * @return array{
     *     company: string,
     *     title: string,
     *     van_type: string,
     *     printed_at: string,
     *     headers: list<string>,
     *     rows: list<list<string>>
     * }
     */
    public function preview(string $vantype, string $sortBy, string $sortDir): array
    {
        $layout = $this->layout($vantype);
        $sortBy = $this->safeSortBy($vantype, $sortBy);
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'desc' : 'asc';

        $columns = array_values(array_unique(array_merge($layout['fields'], [$sortBy])));
        $rows = [];
        $line = 1;
        foreach ($this->inventory->reportRows($vantype, $sortBy, $sortDir, $columns) as $record) {
            $cells = [(string) $line];
            foreach ($layout['fields'] as $field) {
                $cells[] = $this->cellValue($record, $field);
            }
            $rows[] = $cells;
            $line++;
        }

        return [
            'company' => $this->inventory->companyName(),
            'title' => 'Van Inventory Report',
            'van_type' => $vantype,
            'printed_at' => now('Asia/Manila')->format('F j, Y'),
            'headers' => array_merge(['#'], $layout['headers']),
            'rows' => $rows,
        ];
    }

    public function pdf(string $vantype, string $sortBy, string $sortDir): Response
    {
        $layout = $this->layout($vantype);
        $sortBy = $this->safeSortBy($vantype, $sortBy);
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'desc' : 'asc';
        $columns = array_values(array_unique(array_merge($layout['fields'], [$sortBy])));

        return new Response(
            VanInventoryPdf::render(
                $this->inventory->companyName(),
                $vantype,
                now('Asia/Manila')->format('F j, Y'),
                $layout['headers'],
                $layout['positions'],
                $this->formattedPdfRows($vantype, $sortBy, $sortDir, $columns, $layout['fields']),
            ),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="van-inventory.pdf"',
            ],
        );
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $fields
     * @return \Generator<int, array{0: int, 1: list<string>}>
     */
    private function formattedPdfRows(
        string $vantype,
        string $sortBy,
        string $sortDir,
        array $columns,
        array $fields,
    ): \Generator {
        $line = 1;
        foreach ($this->inventory->reportCursor($vantype, $sortBy, $sortDir, $columns) as $record) {
            $cells = [];
            foreach ($fields as $field) {
                $cells[] = $this->cellValue($record, $field);
            }
            yield [$line, $cells];
            $line++;
        }
    }

    /**
     * @return array{headers: list<string>, fields: list<string>, positions: list<int>}
     */
    private function layout(string $vantype): array
    {
        return match (strtoupper($vantype)) {
            'PURCHASED' => [
                'headers' => ['Van #', 'Van Desc.', 'Supplier', 'Date Bought'],
                'fields' => ['prevannum', 'vandsc', 'supplier', 'acqdte'],
                'positions' => [70, 180, 350, 530],
            ],
            'LEASE TO OWN' => [
                'headers' => ['Van #', 'Van Desc.', 'Supplier', 'Date Hired', 'Date Finish'],
                'fields' => ['prevannum', 'vandsc', 'supplier', 'acqdte', 'paydte'],
                'positions' => [60, 150, 300, 450, 530],
            ],
            'CONVERTED' => [
                'headers' => ['Van #', 'Van Desc.', 'Previous Van #'],
                'fields' => ['vannum', 'vandsc', 'prevvannum'],
                'positions' => [60, 150, 450],
            ],
            'FABRICATED' => [
                'headers' => ['Van #', 'Van Desc.', 'Date Fabricated'],
                'fields' => ['prevannum', 'vandsc', 'date_fabricated'],
                'positions' => [60, 150, 450],
            ],
            'RENTAL' => [
                'headers' => ['Van #', 'Van Desc.', 'Supplier', 'Start Rental Date', 'End Rental Date'],
                'fields' => ['prevannum', 'vandsc', 'supplier', 'start_rentaldate', 'end_rentaldate'],
                'positions' => [60, 160, 300, 380, 480],
            ],
            'SCRAPPED' => [
                'headers' => ['Van #', 'Van Desc.', 'Date Scrapped'],
                'fields' => ['prevannum', 'vandsc', 'date_scrapped'],
                'positions' => [60, 160, 300],
            ],
            'SOLD' => [
                'headers' => ['Van #', 'Van Desc.', 'Date Sold'],
                'fields' => ['prevannum', 'vandsc', 'date_sold'],
                'positions' => [60, 160, 300],
            ],
            default => [
                'headers' => ['Van #', 'Van Desc.', 'Van Type', 'Supplier', 'Date Hired', 'Date Finish'],
                'fields' => ['prevannum', 'vandsc', 'vantype', 'supplier', 'acqdte', 'paydte'],
                'positions' => [60, 140, 270, 360, 470, 535],
            ],
        };
    }

    /**
     * @return list<string>
     */
    private function allowedSort(string $vantype): array
    {
        return match (strtoupper($vantype)) {
            'PURCHASED' => ['prevannum', 'vandsc', 'acqdte'],
            'LEASE TO OWN' => ['prevannum', 'vandsc', 'acqdte', 'paydte', 'contractno'],
            'CONVERTED' => ['prevannum', 'vandsc'],
            'FABRICATED' => ['prevannum', 'vandsc', 'date_scrapped'],
            'SCRAPPED' => ['prevannum', 'vandsc', 'date_scrapped'],
            'RENTAL' => ['vannum', 'vandsc', 'start_rentaldate'],
            'SOLD' => ['prevannum', 'vandsc', 'date_sold'],
            default => ['prevannum', 'vandsc', 'acqdte', 'paydte'],
        };
    }

    private function safeSortBy(string $vantype, string $sortBy): string
    {
        $allowed = $this->allowedSort($vantype);

        return in_array($sortBy, $allowed, true) ? $sortBy : $allowed[0];
    }

    private function cellValue(object $record, string $field): string
    {
        $raw = $record->{$field} ?? '';
        if (in_array($field, self::DATE_FIELDS, true)) {
            return $this->displayDate($raw);
        }

        $text = trim((string) $raw);
        if ($field === 'vandsc' && mb_strlen($text) > 25) {
            return mb_substr($text, 0, 25).'...';
        }

        return $text;
    }

    private function displayDate(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return '';
        }

        $stamp = strtotime(substr($text, 0, 10));
        if ($stamp === false) {
            return '';
        }

        return date('n-j-Y', $stamp);
    }
}
