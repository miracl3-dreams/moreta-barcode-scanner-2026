<?php

namespace App\Services;

use App\Http\Requests\StoreShipperRequest;
use App\Models\Shipper;
use App\Repositories\CodeCascadeRepository;
use App\Repositories\ShipperRepository;
use App\Support\CrudList;
use App\Support\Csv;
use App\Support\MasterfilePdf;
use App\Support\Text;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ShipperService
{
    /**
     * @var list<string>
     */
    private const CSV_HEADERS = ['Code', 'Name', 'Tel. No.', 'Address 1', 'TIN No.', 'Password', 'Inactive'];

    /**
     * @var array<string, string>
     */
    private const CSV_ALIASES = [
        'code' => 'cuscde',
        'cuscde' => 'cuscde',
        'name' => 'cusdsc',
        'cusdsc' => 'cusdsc',
        'tel. no.' => 'telno',
        'tel no' => 'telno',
        'telno' => 'telno',
        'address 1' => 'cusadd1',
        'address1' => 'cusadd1',
        'cusadd1' => 'cusadd1',
        'tin no.' => 'tinnum',
        'tin no' => 'tinnum',
        'tinnum' => 'tinnum',
        'password' => 'cuspwd',
        'cuspwd' => 'cuspwd',
        'inactive' => 'inactive',
    ];

    public function __construct(
        private ShipperRepository $shippers,
        private CodeCascadeRepository $cascade,
    ) {}

    /**
     * @return array{data: mixed, meta: array<string, int>}
     */
    public function list(string $search, mixed $perPage, string $sort = '', string $dir = 'asc'): array
    {
        $pageSize = CrudList::perPage($perPage);
        if ($pageSize === 'all') {
            return CrudList::fromCollection($this->shippers->getForList($search, $sort, $dir));
        }

        return CrudList::fromPaginator($this->shippers->paginateForList($search, $pageSize, $sort, $dir));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated): Shipper
    {
        return $this->shippers->create($this->attributesFromRequest($validated, true));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Shipper $shipper, array $validated): Shipper
    {
        $oldCode = (string) ($shipper->cuscde ?? '');
        $oldName = (string) ($shipper->cusdsc ?? '');
        $data = $this->attributesFromRequest($validated, false);

        if (($data['cuspwd'] ?? '') === '') {
            unset($data['cuspwd']);
        }

        $this->shippers->save($shipper, $data);
        $this->cascadeIfChanged(
            $oldCode,
            $oldName,
            (string) $shipper->cuscde,
            (string) $shipper->cusdsc,
        );

        return $shipper;
    }

    public function delete(Shipper $shipper): void
    {
        $this->shippers->delete($shipper);
    }

    public function template(): StreamedResponse
    {
        return Csv::download('shipper-template.csv', [self::CSV_HEADERS]);
    }

    public function pdf(): Response
    {
        return MasterfilePdf::render(
            $this->shippers->companyName(),
            'Shipper',
            ['Code', 'Description'],
            [30, 200],
            [25, 50],
            $this->printRows(),
            'shipper.pdf',
        );
    }

    /**
     * @return \Generator<int, list<string>>
     */
    private function printRows(): \Generator
    {
        foreach ($this->shippers->printCursor() as $shipper) {
            yield [
                trim((string) ($shipper->cuscde ?? '')),
                trim((string) ($shipper->cusdsc ?? '')),
            ];
        }
    }

    public function export(string $search, string $sort = '', string $dir = 'asc'): StreamedResponse
    {
        $rows = [self::CSV_HEADERS];
        foreach ($this->shippers->getForList($search, $sort, $dir) as $shipper) {
            $rows[] = [
                (string) ($shipper->cuscde ?? ''),
                (string) ($shipper->cusdsc ?? ''),
                (string) ($shipper->telno ?? ''),
                (string) ($shipper->cusadd1 ?? ''),
                (string) ($shipper->tinnum ?? ''),
                '',
                (int) ($shipper->inactive ?? 0) === 1 ? '1' : '0',
            ];
        }

        return Csv::download('shippers.csv', $rows);
    }

    /**
     * @return array{imported?: int, errors?: list<array{line: int, code: string, message: string}>, message?: string, status?: int}
     */
    public function import(string $path): array
    {
        $csv = Csv::read($path);
        if (! $csv['ok']) {
            return ['message' => $csv['message'], 'status' => 422];
        }

        $map = Csv::headerMap($csv['header'], self::CSV_ALIASES);
        if (! isset($map['cuscde']) || ! isset($map['cusdsc'])) {
            return ['message' => 'Invalid template. Required columns: Code, Name.', 'status' => 422];
        }

        $imported = 0;
        $errors = [];
        $line = 1;

        foreach ($csv['rows'] as $row) {
            $line++;
            if (Csv::rowEmpty($row)) {
                continue;
            }

            $payload = [
                'cuscde' => Csv::cell($row, $map, 'cuscde'),
                'cusdsc' => Csv::cell($row, $map, 'cusdsc'),
                'telno' => Csv::cell($row, $map, 'telno'),
                'cusadd1' => Csv::cell($row, $map, 'cusadd1'),
                'tinnum' => Csv::cell($row, $map, 'tinnum'),
                'cuspwd' => Csv::cell($row, $map, 'cuspwd'),
                'inactive' => $this->parseInactive(Csv::cell($row, $map, 'inactive')),
            ];

            $validator = Validator::make(
                $payload,
                (new StoreShipperRequest)->rules(),
                (new StoreShipperRequest)->messages()
            );
            if ($validator->fails()) {
                $errors[] = [
                    'line' => $line,
                    'code' => $payload['cuscde'],
                    'message' => collect($validator->errors()->all())->implode(' '),
                ];

                continue;
            }

            try {
                $this->shippers->create($this->attributesFromRequest($validator->validated(), true));
                $imported++;
            } catch (Throwable) {
                $errors[] = [
                    'line' => $line,
                    'code' => $payload['cuscde'],
                    'message' => 'Unable to save this row.',
                ];
            }
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributesFromRequest(array $validated, bool $includePassword): array
    {
        $data = [
            'cuscde' => Text::clip($validated['cuscde'] ?? '', 25),
            'cusdsc' => Text::clip($validated['cusdsc'] ?? '', 100),
            'telno' => Text::clip($validated['telno'] ?? '', 30),
            'cusadd1' => Text::clip($validated['cusadd1'] ?? '', 100),
            'tinnum' => Text::clip($validated['tinnum'] ?? '', 30),
            'inactive' => $this->parseInactive($validated['inactive'] ?? 0),
        ];

        if ($includePassword || array_key_exists('cuspwd', $validated)) {
            $data['cuspwd'] = Text::clip($validated['cuspwd'] ?? '', 30);
        }

        return $data;
    }

    private function parseInactive(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $text = strtolower(trim((string) ($value ?? '')));

        return in_array($text, ['1', 'true', 'yes', 'y', 'on'], true) ? 1 : 0;
    }

    private function cascadeIfChanged(string $oldCode, string $oldName, string $newCode, string $newName): void
    {
        if ($oldCode === '' || ($oldCode === $newCode && $oldName === $newName)) {
            return;
        }

        $this->cascade->cascade('customerfile', 'cuscde', 'cusdsc', $oldCode, $newCode, $newName);
    }
}
