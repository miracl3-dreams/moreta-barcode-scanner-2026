<?php

namespace App\Services;

use App\Repositories\LoadListRepository;
use App\Support\LoadListPdf;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoadListService
{
    public function __construct(private LoadListRepository $loadList) {}

    /**
     * @return array{categories: list<array{catcde: string}>}
     */
    public function lookups(): array
    {
        return [
            'categories' => $this->loadList->categoriesInBl(),
        ];
    }

    public function officePdf(string $voynum, string $catcde): Response
    {
        $payload = $this->payload($voynum, $catcde, 'office');

        return new Response(
            LoadListPdf::office()->render($payload),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="load-list-office.pdf"',
            ],
        );
    }

    public function checkerPdf(string $voynum, string $catcde): Response
    {
        $payload = $this->payload($voynum, $catcde, 'checker');

        return new Response(
            LoadListPdf::checker()->render($payload),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="load-list-checker.pdf"',
            ],
        );
    }

    public function officeExport(string $voynum, string $catcde): StreamedResponse
    {
        return $this->export('office', $voynum, $catcde, 'Office_Copy.txt');
    }

    public function checkerExport(string $voynum, string $catcde): StreamedResponse
    {
        return $this->export('checker', $voynum, $catcde, 'Checker_Copy.txt');
    }

    /**
     * @return array{
     *     copy: string,
     *     company: string,
     *     voynum: string,
     *     catcde: string,
     *     sailing_date: string,
     *     vessel: string,
     *     printed_at: string,
     *     headers: list<string>,
     *     positions: list<int>,
     *     rows: list<list<array{value: string, field: string}>>
     * }
     */
    private function payload(string $voynum, string $catcde, string $copy): array
    {
        $voynum = trim($voynum);
        $catcde = trim($catcde);
        if ($voynum === '' || ! $this->loadList->voyageExists($voynum)) {
            throw ValidationException::withMessages([
                'voynum' => 'Invalid voyage number.',
            ]);
        }

        $category = $this->loadList->category($catcde);
        $columns = self::columns(
            $copy,
            (string) ($category['iscontainer'] ?? ''),
            $category['catcde'] ?? $catcde,
        );
        if (isset($columns['error'])) {
            throw ValidationException::withMessages([
                'catcde' => $columns['error'],
            ]);
        }

        $sailing = $this->loadList->sailing($voynum);
        $lines = $this->loadList->lines($voynum, $catcde);
        $shippers = $this->loadList->shipperNames(
            $lines->map(fn ($row) => trim((string) ($row->cuscde ?? '')))->all(),
        );
        $consignees = $this->loadList->consigneeNames(
            $lines->map(fn ($row) => trim((string) ($row->concde ?? '')))->all(),
        );

        $rows = [];
        $number = 1;
        foreach ($lines as $line) {
            $cells = [];
            foreach ($columns['fields'] as $field) {
                $cells[] = [
                    'field' => $field,
                    'value' => $this->cellValue($field, $line, $number, $shippers, $consignees, $copy === 'office'),
                ];
            }
            $rows[] = $cells;
            $number++;
        }

        $printed = $copy === 'checker'
            ? now('Asia/Manila')->format('F j, Y, g:i A')
            : now('Asia/Manila')->format('F j, Y g:i A');

        return [
            'copy' => $copy,
            'company' => $this->loadList->companyName(),
            'voynum' => $voynum,
            'catcde' => $catcde,
            'sailing_date' => $sailing['date'],
            'vessel' => $sailing['vessel'],
            'printed_at' => $printed,
            'headers' => $columns['headers'],
            'positions' => $columns['positions'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{error: string}|array{headers: list<string>, fields: list<string>, positions: list<int>}
     */
    public static function columns(string $copy, string $iscontainer, string $catcde): array
    {
        $cat = strtoupper(trim($catcde));
        $container = strtoupper(trim($iscontainer)) === 'Y';
        if ($copy === 'checker') {
            if ($container) {
                return [
                    'headers' => ['No.', 'Van No.', 'Description', 'B/L #', 'Tons/CBM'],
                    'fields' => ['no', 'vannum', 'classdsc', 'docnum', 'weiamt'],
                    'positions' => [25, 70, 200, 400, 500],
                ];
            }
            if ($cat === 'HEAVY EQUIPMENT') {
                return [
                    'headers' => ['No.', 'Description', 'B/L #', 'Tons/CBM'],
                    'fields' => ['no', 'classdsc', 'docnum', 'weiamt'],
                    'positions' => [25, 80, 300, 400],
                ];
            }
            if ($cat === 'LCL-IN CONTAINER') {
                return ['error' => 'Report not Applicable'];
            }
            if ($cat === 'LCL-NOT IN CONTAINER') {
                return [
                    'headers' => ['No.', 'Description', 'B/L #', 'Tons/CBM'],
                    'fields' => ['no', 'classdsc', 'docnum', 'weiamt'],
                    'positions' => [25, 80, 300, 400],
                ];
            }
            if ($cat === 'VEHICLES IN CONTAINER') {
                return [
                    'headers' => ['No.', 'Van No.', 'Description', 'Plate # / Conduction #', 'B/L #', 'Tons/CBM'],
                    'fields' => ['no', 'vannum', 'classdsc', 'plate', 'docnum', 'weiamt'],
                    'positions' => [25, 70, 170, 330, 460, 530],
                ];
            }
            if ($cat === 'VEHICLES NOT IN CONTAINERS') {
                return [
                    'headers' => ['No.', 'Description', 'Plate # / Conduction #', 'B/L #', 'Tons/CBM'],
                    'fields' => ['no', 'classdsc', 'plate', 'docnum', 'weiamt'],
                    'positions' => [25, 70, 230, 410, 520],
                ];
            }

            return [
                'headers' => ['No.', 'Van No.', 'Description', 'Plate # / Conduction #', 'B/L #', 'Tons/CBM'],
                'fields' => ['no', 'vannum', 'classdsc', 'plate', 'docnum', 'weiamt'],
                'positions' => [25, 70, 170, 330, 460, 530],
            ];
        }

        if ($container) {
            return [
                'headers' => ['No.', 'Van No.', 'Shipper', 'Consignee', 'Description', 'B/L #', 'Tons/CBM', 'Qty'],
                'fields' => ['no', 'vannum', 'cuscde', 'concde', 'classdsc', 'docnum', 'weiamt', 'qty'],
                'positions' => [25, 50, 180, 350, 500, 660, 800, 880],
            ];
        }
        if ($cat === 'HEAVY EQUIPMENT') {
            return [
                'headers' => ['No.', 'Shipper', 'Consignee', 'Description', 'B/L #', 'Tons/CBM', 'Qty'],
                'fields' => ['no', 'cuscde', 'concde', 'classdsc', 'docnum', 'weiamt', 'qty'],
                'positions' => [25, 50, 250, 460, 660, 800, 880],
            ];
        }
        if ($cat === 'LCL-IN CONTAINER') {
            return ['error' => 'Report not Applicable'];
        }
        if ($cat === 'LCL-NOT IN CONTAINER') {
            return [
                'headers' => ['No.', 'Shipper', 'Consignee', 'Description', 'B/L #', 'Tons/CBM', 'Qty'],
                'fields' => ['no', 'cuscde', 'concde', 'classdsc', 'docnum', 'weiamt', 'qty'],
                'positions' => [25, 70, 250, 460, 660, 780, 880],
            ];
        }
        if ($cat === 'VEHICLES IN CONTAINER') {
            return [
                'headers' => ['No.', 'Van No.', 'Shipper', 'Consignee', 'Description', 'Plate # / Conduction #', 'B/L #', 'Tons/CBM', 'Qty'],
                'fields' => ['no', 'vannum', 'cuscde', 'concde', 'classdsc', 'plate', 'docnum', 'weiamt', 'qty'],
                'positions' => [25, 50, 150, 320, 450, 570, 720, 820, 880],
            ];
        }
        if ($cat === 'VEHICLES NOT IN CONTAINERS') {
            return [
                'headers' => ['No.', 'Shipper', 'Consignee', 'Description', 'Plate # / Conduction #', 'B/L #', 'Tons/CBM', 'Qty'],
                'fields' => ['no', 'cuscde', 'concde', 'classdsc', 'plate', 'docnum', 'weiamt', 'qty'],
                'positions' => [25, 70, 220, 340, 540, 700, 800, 880],
            ];
        }

        return [
            'headers' => ['No.', 'Van No.', 'Shipper', 'Consignee', 'Description', 'Plate # / Conduction #', 'B/L #', 'Tons/CBM', 'Qty'],
            'fields' => ['no', 'vannum', 'cuscde', 'concde', 'classdsc', 'plate', 'docnum', 'weiamt', 'qty'],
            'positions' => [25, 50, 150, 320, 450, 580, 720, 800, 880],
        ];
    }

    /**
     * @param  array<string, string>  $shippers
     * @param  array<string, string>  $consignees
     */
    private function cellValue(string $field, object $line, int $number, array $shippers, array $consignees, bool $office): string
    {
        if ($field === 'no') {
            return $number.'.';
        }

        $value = trim((string) ($line->{$field} ?? ''));
        if ($field === 'vannum') {
            $soc = trim((string) ($line->socvannum ?? ''));

            return $soc !== '' ? $soc : $value;
        }
        if ($field === 'classdsc') {
            if ($office) {
                return substr($value, 0, 30);
            }

            return substr($value, 0, 25);
        }
        if ($field === 'cuscde') {
            $name = $shippers[trim((string) ($line->cuscde ?? ''))] ?? '';

            return $office ? substr($name, 0, 27) : $name;
        }
        if ($field === 'concde') {
            $name = $consignees[trim((string) ($line->concde ?? ''))] ?? '';

            return $office ? substr($name, 0, 20) : $name;
        }
        if ($field === 'docnum') {
            $code = strtoupper(trim((string) ($line->trncde ?? '')));
            $doc = $code === 'EV' ? 'EMPTY VAN' : trim((string) ($line->docnum ?? ''));

            return substr($doc, 0, 20);
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $shippers
     * @param  array<string, string>  $consignees
     */
    private function exportCell(string $field, object $line, int $number, array $shippers, array $consignees): string
    {
        if ($field === 'no') {
            return $number.'.';
        }
        if ($field === 'vannum') {
            $soc = trim((string) ($line->socvannum ?? ''));

            return $soc !== '' ? $soc : trim((string) ($line->vannum ?? ''));
        }
        if ($field === 'cuscde') {
            return $shippers[trim((string) ($line->cuscde ?? ''))] ?? '';
        }
        if ($field === 'concde') {
            return $consignees[trim((string) ($line->concde ?? ''))] ?? '';
        }
        if ($field === 'docnum') {
            $code = strtoupper(trim((string) ($line->trncde ?? '')));

            return $code === 'EV' ? 'EMPTY VAN' : trim((string) ($line->docnum ?? ''));
        }

        return trim((string) ($line->{$field} ?? ''));
    }

    private function export(string $copy, string $voynum, string $catcde, string $filename): StreamedResponse
    {
        $payload = $this->payload($voynum, $catcde, $copy);
        $lines = $this->loadList->lines($payload['voynum'], $payload['catcde']);
        $shippers = $this->loadList->shipperNames(
            $lines->map(fn ($row) => trim((string) ($row->cuscde ?? '')))->all(),
        );
        $consignees = $this->loadList->consigneeNames(
            $lines->map(fn ($row) => trim((string) ($row->concde ?? '')))->all(),
        );
        $category = $this->loadList->category($payload['catcde']);
        $columns = self::columns($copy, (string) ($category['iscontainer'] ?? ''), $payload['catcde']);
        if (isset($columns['error'])) {
            throw ValidationException::withMessages([
                'catcde' => $columns['error'],
            ]);
        }

        $tab = "\t";
        $eol = "\r\n";
        $title = $copy === 'checker' ? 'Load List (CHECKER COPY)' : 'Load List (OFFICE COPY)';
        $printedLabel = $copy === 'checker'
            ? 'Date Printed :'.$payload['printed_at']
            : 'Date Printed :'.$payload['printed_at'];

        $chunk = $payload['company'].$tab
            .$eol.$title.$tab
            .$eol.'Voyage No. : '.$payload['voynum'].$tab
            .$eol.'Cargo Category : '.$payload['catcde'].$tab
            .$eol.'Sailing Date : '.$payload['sailing_date'].$tab
            .$eol.'Vessel : '.$payload['vessel'].$tab
            .$eol.$printedLabel.$tab
            .$eol.$eol;

        foreach ($columns['headers'] as $header) {
            $chunk .= $header.$tab;
        }
        $chunk .= $eol;

        $number = 1;
        foreach ($lines as $line) {
            foreach ($columns['fields'] as $field) {
                $chunk .= $this->exportCell($field, $line, $number, $shippers, $consignees).$tab;
            }
            $chunk .= $eol;
            $number++;
        }

        return response()->streamDownload(function () use ($chunk) {
            echo $chunk;
        }, $filename, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }
}
