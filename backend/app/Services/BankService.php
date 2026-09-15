<?php

namespace App\Services;

use App\Http\Requests\StoreBankRequest;
use App\Models\Bank;
use App\Repositories\BankRepository;
use App\Repositories\CodeCascadeRepository;
use App\Support\CrudList;
use App\Support\Csv;
use App\Support\Text;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BankService
{
    /**
     * @var list<string>
     */
    private const CSV_HEADERS = ['Code', 'Description', 'GL Account'];

    /**
     * @var array<string, string>
     */
    private const CSV_ALIASES = [
        'code' => 'bnkcde',
        'bnkcde' => 'bnkcde',
        'description' => 'bnkdsc',
        'bnkdsc' => 'bnkdsc',
        'gl account' => 'actcde',
        'actcde' => 'actcde',
    ];

    public function __construct(
        private BankRepository $banks,
        private CodeCascadeRepository $cascade,
    ) {}

    /**
     * @return array{data: mixed, meta: array<string, int>}
     */
    public function list(string $search, mixed $perPage, string $sort = '', string $dir = 'asc'): array
    {
        $pageSize = CrudList::perPage($perPage);
        if ($pageSize === 'all') {
            return CrudList::fromCollection($this->banks->getForList($search, $sort, $dir));
        }

        return CrudList::fromPaginator($this->banks->paginateForList($search, $pageSize, $sort, $dir));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated): Bank
    {
        return $this->banks->create($this->attributesFromRequest($validated));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Bank $bank, array $validated): Bank
    {
        $oldCode = (string) ($bank->bnkcde ?? '');
        $oldName = (string) ($bank->bnkdsc ?? '');
        $this->banks->save($bank, $this->attributesFromRequest($validated));
        $this->cascadeIfChanged(
            $oldCode,
            $oldName,
            (string) $bank->bnkcde,
            (string) $bank->bnkdsc,
        );

        return $bank;
    }

    public function delete(Bank $bank): void
    {
        $this->banks->delete($bank);
    }

    public function template(): StreamedResponse
    {
        return Csv::download('bank-template.csv', [self::CSV_HEADERS]);
    }

    public function export(string $search, string $sort = '', string $dir = 'asc'): StreamedResponse
    {
        $rows = [self::CSV_HEADERS];
        foreach ($this->banks->getForList($search, $sort, $dir) as $bank) {
            $rows[] = [
                (string) ($bank->bnkcde ?? ''),
                (string) ($bank->bnkdsc ?? ''),
                (string) ($bank->actcde ?? ''),
            ];
        }

        return Csv::download('banks.csv', $rows);
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
        if (! isset($map['bnkcde']) || ! isset($map['bnkdsc']) || ! isset($map['actcde'])) {
            return ['message' => 'Invalid template. Required columns: Code, Description, GL Account.', 'status' => 422];
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
                'bnkcde' => Csv::cell($row, $map, 'bnkcde'),
                'bnkdsc' => Csv::cell($row, $map, 'bnkdsc'),
                'actcde' => Csv::cell($row, $map, 'actcde'),
            ];

            $validator = Validator::make(
                $payload,
                (new StoreBankRequest)->rules(),
                (new StoreBankRequest)->messages()
            );
            if ($validator->fails()) {
                $errors[] = [
                    'line' => $line,
                    'code' => $payload['bnkcde'],
                    'message' => collect($validator->errors()->all())->implode(' '),
                ];

                continue;
            }

            try {
                $this->banks->create($this->attributesFromRequest($validator->validated()));
                $imported++;
            } catch (Throwable) {
                $errors[] = [
                    'line' => $line,
                    'code' => $payload['bnkcde'],
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
            'bnkcde' => Text::clip($validated['bnkcde'] ?? '', 25),
            'bnkdsc' => Text::clip($validated['bnkdsc'] ?? '', 30),
            'actcde' => Text::clip($validated['actcde'] ?? '', 30),
        ];
    }

    private function cascadeIfChanged(string $oldCode, string $oldName, string $newCode, string $newName): void
    {
        if ($oldCode === '' || ($oldCode === $newCode && $oldName === $newName)) {
            return;
        }

        $this->cascade->cascade('bankfile', 'bnkcde', 'bnkdsc', $oldCode, $newCode, $newName);
    }
}
