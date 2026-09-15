<?php

namespace App\Services;

use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use App\Repositories\CategoryRepository;
use App\Repositories\CodeCascadeRepository;
use App\Support\CrudList;
use App\Support\Csv;
use App\Support\Text;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CategoryService
{
    /**
     * @var list<string>
     */
    private const CSV_HEADERS = [
        'Code',
        'Description',
        'Has Maximum',
        'Max Value (Decimal)',
        'Container',
        'Unit Measure',
        'Measure Amount',
        'Tag',
    ];

    /**
     * @var array<string, string>
     */
    private const CSV_ALIASES = [
        'code' => 'catcde',
        'catcde' => 'catcde',
        'description' => 'catdsc',
        'catdsc' => 'catdsc',
        'has maximum' => 'hasmax',
        'hasmax' => 'hasmax',
        'max value (decimal)' => 'decvalmax',
        'decvalmax' => 'decvalmax',
        'container' => 'iscontainer',
        'iscontainer' => 'iscontainer',
        'unit measure' => 'untmea',
        'untmea' => 'untmea',
        'measure amount' => 'meaamt',
        'meaamt' => 'meaamt',
        'tag' => 'tag',
    ];

    public function __construct(
        private CategoryRepository $categories,
        private CodeCascadeRepository $cascade,
    ) {}

    /**
     * @return array{data: mixed, meta: array<string, int>}
     */
    public function list(string $search, mixed $perPage, string $sort = '', string $dir = 'asc'): array
    {
        $pageSize = CrudList::perPage($perPage);
        if ($pageSize === 'all') {
            return CrudList::fromCollection($this->categories->getForList($search, $sort, $dir));
        }

        return CrudList::fromPaginator($this->categories->paginateForList($search, $pageSize, $sort, $dir));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated): Category
    {
        return $this->categories->create($this->attributesFromRequest($validated));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Category $category, array $validated): Category
    {
        $oldCode = (string) ($category->catcde ?? '');
        $oldName = (string) ($category->catdsc ?? '');
        $this->categories->save($category, $this->attributesFromRequest($validated));
        $this->cascadeIfChanged(
            $oldCode,
            $oldName,
            (string) $category->catcde,
            (string) $category->catdsc,
        );

        return $category;
    }

    public function delete(Category $category): void
    {
        $this->categories->delete($category);
    }

    public function template(): StreamedResponse
    {
        return Csv::download('category-template.csv', [self::CSV_HEADERS]);
    }

    public function export(string $search, string $sort = '', string $dir = 'asc'): StreamedResponse
    {
        $rows = [self::CSV_HEADERS];
        foreach ($this->categories->getForList($search, $sort, $dir) as $category) {
            $row = $category->toListArray();
            $rows[] = [
                $row['catcde'],
                $row['catdsc'],
                $row['hasmax'],
                $row['decvalmax'],
                $row['iscontainer'],
                $row['untmea'],
                $row['meaamt'],
                $row['tag'],
            ];
        }

        return Csv::download('categories.csv', $rows);
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
        foreach (['catcde', 'catdsc', 'hasmax', 'decvalmax', 'iscontainer', 'untmea', 'meaamt', 'tag'] as $field) {
            if (! isset($map[$field])) {
                return [
                    'message' => 'Invalid template. Required columns: Code, Description, Has Maximum, Max Value (Decimal), Container, Unit Measure, Measure Amount, Tag.',
                    'status' => 422,
                ];
            }
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
                'catcde' => Csv::cell($row, $map, 'catcde'),
                'catdsc' => Csv::cell($row, $map, 'catdsc'),
                'hasmax' => Csv::cell($row, $map, 'hasmax'),
                'decvalmax' => Csv::cell($row, $map, 'decvalmax'),
                'iscontainer' => Csv::cell($row, $map, 'iscontainer'),
                'untmea' => Csv::cell($row, $map, 'untmea'),
                'meaamt' => Csv::cell($row, $map, 'meaamt'),
                'tag' => Csv::cell($row, $map, 'tag'),
            ];

            $validator = Validator::make(
                $payload,
                (new StoreCategoryRequest)->rules(),
                (new StoreCategoryRequest)->messages()
            );
            if ($validator->fails()) {
                $errors[] = [
                    'line' => $line,
                    'code' => $payload['catcde'],
                    'message' => collect($validator->errors()->all())->implode(' '),
                ];

                continue;
            }

            try {
                $this->categories->create($this->attributesFromRequest($validator->validated()));
                $imported++;
            } catch (Throwable) {
                $errors[] = [
                    'line' => $line,
                    'code' => $payload['catcde'],
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
            'catcde' => Text::clip($validated['catcde'] ?? '', 50),
            'catdsc' => Text::clip($validated['catdsc'] ?? '', 100),
            'hasmax' => Text::clip($validated['hasmax'] ?? '', 1),
            'decvalmax' => (float) ($validated['decvalmax'] ?? 0),
            'iscontainer' => Text::clip($validated['iscontainer'] ?? '', 1),
            'untmea' => Text::clip($validated['untmea'] ?? '', 10),
            'meaamt' => (float) ($validated['meaamt'] ?? 0),
            'tag' => Text::clip($validated['tag'] ?? '', 25),
        ];
    }

    private function cascadeIfChanged(string $oldCode, string $oldName, string $newCode, string $newName): void
    {
        if ($oldCode === '' || ($oldCode === $newCode && $oldName === $newName)) {
            return;
        }

        $this->cascade->cascade('categoryfile', 'catcde', 'catdsc', $oldCode, $newCode, $newName);
    }
}
