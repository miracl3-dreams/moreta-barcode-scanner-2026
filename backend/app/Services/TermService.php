<?php

namespace App\Services;

use App\Http\Requests\StoreTermRequest;
use App\Models\Term;
use App\Repositories\CodeCascadeRepository;
use App\Repositories\TermRepository;
use App\Support\CrudList;
use App\Support\Csv;
use App\Support\Text;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TermService
{
    /**
     * @var list<string>
     */
    private const CSV_HEADERS = ['Code', 'Description', 'Day'];

    /**
     * @var array<string, string>
     */
    private const CSV_ALIASES = [
        'code' => 'trmcde',
        'trmcde' => 'trmcde',
        'description' => 'trmdsc',
        'trmdsc' => 'trmdsc',
        'day' => 'trmday',
        'trmday' => 'trmday',
    ];

    public function __construct(
        private TermRepository $terms,
        private CodeCascadeRepository $cascade,
    ) {}

    /**
     * @return array{data: mixed, meta: array<string, int>}
     */
    public function list(string $search, mixed $perPage, string $sort = '', string $dir = 'asc'): array
    {
        $pageSize = CrudList::perPage($perPage);
        if ($pageSize === 'all') {
            return CrudList::fromCollection($this->terms->getForList($search, $sort, $dir));
        }

        return CrudList::fromPaginator($this->terms->paginateForList($search, $pageSize, $sort, $dir));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated): Term
    {
        return $this->terms->create($this->attributesFromRequest($validated));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Term $term, array $validated): Term
    {
        $oldCode = (string) ($term->trmcde ?? '');
        $oldName = (string) ($term->trmdsc ?? '');
        $this->terms->save($term, $this->attributesFromRequest($validated));
        $this->cascadeIfChanged(
            $oldCode,
            $oldName,
            (string) $term->trmcde,
            (string) $term->trmdsc,
        );

        return $term;
    }

    public function delete(Term $term): void
    {
        $this->terms->delete($term);
    }

    public function template(): StreamedResponse
    {
        return Csv::download('term-template.csv', [self::CSV_HEADERS]);
    }

    public function export(string $search, string $sort = '', string $dir = 'asc'): StreamedResponse
    {
        $rows = [self::CSV_HEADERS];
        foreach ($this->terms->getForList($search, $sort, $dir) as $term) {
            $row = $term->toListArray();
            $rows[] = [
                $row['trmcde'],
                $row['trmdsc'],
                $row['trmday'],
            ];
        }

        return Csv::download('terms.csv', $rows);
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
        if (! isset($map['trmcde']) || ! isset($map['trmdsc']) || ! isset($map['trmday'])) {
            return ['message' => 'Invalid template. Required columns: Code, Description, Day.', 'status' => 422];
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
                'trmcde' => Csv::cell($row, $map, 'trmcde'),
                'trmdsc' => Csv::cell($row, $map, 'trmdsc'),
                'trmday' => Csv::cell($row, $map, 'trmday'),
            ];

            $validator = Validator::make(
                $payload,
                (new StoreTermRequest)->rules(),
                (new StoreTermRequest)->messages()
            );
            if ($validator->fails()) {
                $errors[] = [
                    'line' => $line,
                    'code' => $payload['trmcde'],
                    'message' => collect($validator->errors()->all())->implode(' '),
                ];

                continue;
            }

            try {
                $this->terms->create($this->attributesFromRequest($validator->validated()));
                $imported++;
            } catch (Throwable) {
                $errors[] = [
                    'line' => $line,
                    'code' => $payload['trmcde'],
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
            'trmcde' => Text::clip($validated['trmcde'] ?? '', 20),
            'trmdsc' => Text::clip($validated['trmdsc'] ?? '', 30),
            'trmday' => (float) ($validated['trmday'] ?? 0),
        ];
    }

    private function cascadeIfChanged(string $oldCode, string $oldName, string $newCode, string $newName): void
    {
        if ($oldCode === '' || ($oldCode === $newCode && $oldName === $newName)) {
            return;
        }

        $this->cascade->cascade('termfile', 'trmcde', 'trmdsc', $oldCode, $newCode, $newName);
    }
}
