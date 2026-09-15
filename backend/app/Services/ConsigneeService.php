<?php

namespace App\Services;

use App\Http\Requests\StoreConsigneeRequest;
use App\Models\Consignee;
use App\Repositories\CodeCascadeRepository;
use App\Repositories\ConsigneeRepository;
use App\Support\CrudList;
use App\Support\Csv;
use App\Support\MasterfilePdf;
use App\Support\Text;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ConsigneeService
{
    /**
     * @var list<string>
     */
    private const CSV_HEADERS = ['Code', 'Description', 'Tel. No.', 'Address 1', 'TIN No.'];

    /**
     * @var array<string, string>
     */
    private const CSV_ALIASES = [
        'code' => 'concde',
        'concde' => 'concde',
        'description' => 'condsc',
        'condsc' => 'condsc',
        'tel. no.' => 'telnum',
        'tel no' => 'telnum',
        'telnum' => 'telnum',
        'address 1' => 'conadd1',
        'address1' => 'conadd1',
        'conadd1' => 'conadd1',
        'tin no.' => 'tinnum',
        'tin no' => 'tinnum',
        'tinnum' => 'tinnum',
    ];

    public function __construct(
        private ConsigneeRepository $consignees,
        private CodeCascadeRepository $cascade,
    ) {}

    /**
     * @return array{data: mixed, meta: array<string, int>}
     */
    public function list(string $search, mixed $perPage, string $sort = '', string $dir = 'asc'): array
    {
        $pageSize = CrudList::perPage($perPage);
        if ($pageSize === 'all') {
            return CrudList::fromCollection($this->consignees->getForList($search, $sort, $dir));
        }

        return CrudList::fromPaginator($this->consignees->paginateForList($search, $pageSize, $sort, $dir));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated): Consignee
    {
        return $this->consignees->create($this->attributesFromRequest($validated));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Consignee $consignee, array $validated): Consignee
    {
        $oldCode = (string) ($consignee->concde ?? '');
        $oldName = (string) ($consignee->condsc ?? '');
        $this->consignees->save($consignee, $this->attributesFromRequest($validated));
        $this->cascadeIfChanged(
            $oldCode,
            $oldName,
            (string) $consignee->concde,
            (string) $consignee->condsc,
        );

        return $consignee;
    }

    public function delete(Consignee $consignee): void
    {
        $this->consignees->delete($consignee);
    }

    public function template(): StreamedResponse
    {
        return Csv::download('consignee-template.csv', [self::CSV_HEADERS]);
    }

    public function pdf(): Response
    {
        return MasterfilePdf::render(
            $this->consignees->companyName(),
            'Consignee Master Maintainance',
            ['Code', 'Description'],
            [30, 200],
            [25, 50],
            $this->printRows(),
            'consignee.pdf',
        );
    }

    /**
     * @return \Generator<int, list<string>>
     */
    private function printRows(): \Generator
    {
        foreach ($this->consignees->printCursor() as $consignee) {
            yield [
                trim((string) ($consignee->concde ?? '')),
                trim((string) ($consignee->condsc ?? '')),
            ];
        }
    }

    public function export(string $search, string $sort = '', string $dir = 'asc'): StreamedResponse
    {
        $rows = [self::CSV_HEADERS];
        foreach ($this->consignees->getForList($search, $sort, $dir) as $consignee) {
            $rows[] = [
                (string) ($consignee->concde ?? ''),
                (string) ($consignee->condsc ?? ''),
                (string) ($consignee->telnum ?? ''),
                (string) ($consignee->conadd1 ?? ''),
                (string) ($consignee->tinnum ?? ''),
            ];
        }

        return Csv::download('consignees.csv', $rows);
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
        if (! isset($map['concde']) || ! isset($map['condsc'])) {
            return ['message' => 'Invalid template. Required columns: Code, Description.', 'status' => 422];
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
                'concde' => Csv::cell($row, $map, 'concde'),
                'condsc' => Csv::cell($row, $map, 'condsc'),
                'telnum' => Csv::cell($row, $map, 'telnum'),
                'conadd1' => Csv::cell($row, $map, 'conadd1'),
                'tinnum' => Csv::cell($row, $map, 'tinnum'),
            ];

            $validator = Validator::make(
                $payload,
                (new StoreConsigneeRequest)->rules(),
                (new StoreConsigneeRequest)->messages()
            );
            if ($validator->fails()) {
                $errors[] = [
                    'line' => $line,
                    'code' => $payload['concde'],
                    'message' => collect($validator->errors()->all())->implode(' '),
                ];

                continue;
            }

            try {
                $this->consignees->create($this->attributesFromRequest($validator->validated()));
                $imported++;
            } catch (Throwable) {
                $errors[] = [
                    'line' => $line,
                    'code' => $payload['concde'],
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
    private function attributesFromRequest(array $validated): array
    {
        return [
            'concde' => Text::clip($validated['concde'] ?? '', 25),
            'condsc' => Text::clip($validated['condsc'] ?? '', 100),
            'telnum' => Text::clip($validated['telnum'] ?? '', 100),
            'conadd1' => Text::clip($validated['conadd1'] ?? '', 100),
            'tinnum' => Text::clip($validated['tinnum'] ?? '', 30),
        ];
    }

    private function cascadeIfChanged(string $oldCode, string $oldName, string $newCode, string $newName): void
    {
        if ($oldCode === '' || ($oldCode === $newCode && $oldName === $newName)) {
            return;
        }

        $this->cascade->cascade('consigneefile', 'concde', 'condsc', $oldCode, $newCode, $newName);
    }
}
