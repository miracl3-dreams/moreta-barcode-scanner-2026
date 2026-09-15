<?php

namespace App\Services;

use App\Http\Requests\StoreCheckerRequest;
use App\Models\Checker;
use App\Repositories\CheckerRepository;
use App\Support\CrudList;
use App\Support\Csv;
use App\Support\Text;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CheckerService
{
    /**
     * @var list<string>
     */
    private const CSV_HEADERS = ['Name'];

    /**
     * @var array<string, string>
     */
    private const CSV_ALIASES = [
        'name' => 'cuscde',
        'checker' => 'cuscde',
        'cuscde' => 'cuscde',
    ];

    public function __construct(private CheckerRepository $checkers) {}

    /**
     * @return array{data: mixed, meta: array<string, int>}
     */
    public function list(string $search, mixed $perPage, string $sort = '', string $dir = 'asc'): array
    {
        $pageSize = CrudList::perPage($perPage);
        if ($pageSize === 'all') {
            return CrudList::fromCollection($this->checkers->getForList($search, $sort, $dir));
        }

        return CrudList::fromPaginator($this->checkers->paginateForList($search, $pageSize, $sort, $dir));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated): Checker
    {
        return $this->checkers->create($this->attributesFromRequest($validated));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Checker $checker, array $validated): Checker
    {
        return $this->checkers->save($checker, $this->attributesFromRequest($validated));
    }

    public function delete(Checker $checker): void
    {
        $this->checkers->delete($checker);
    }

    public function template(): StreamedResponse
    {
        return Csv::download('checker-template.csv', [self::CSV_HEADERS]);
    }

    public function export(string $search, string $sort = '', string $dir = 'asc'): StreamedResponse
    {
        $rows = [self::CSV_HEADERS];
        foreach ($this->checkers->getForList($search, $sort, $dir) as $checker) {
            $rows[] = [
                (string) ($checker->cuscde ?? ''),
            ];
        }

        return Csv::download('checkers.csv', $rows);
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
        if (! isset($map['cuscde'])) {
            return ['message' => 'Invalid template. Required columns: Name.', 'status' => 422];
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
            ];

            $validator = Validator::make(
                $payload,
                (new StoreCheckerRequest)->rules(),
                (new StoreCheckerRequest)->messages()
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
                $this->checkers->create($this->attributesFromRequest($validator->validated()));
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
    private function attributesFromRequest(array $validated): array
    {
        return [
            'cuscde' => Text::clip($validated['cuscde'] ?? '', 25),
        ];
    }
}
