<?php

namespace App\Services;

use App\Http\Requests\StorePrefixRequest;
use App\Models\Prefix;
use App\Repositories\PrefixRepository;
use App\Support\CrudList;
use App\Support\Csv;
use App\Support\Text;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PrefixService
{
    /**
     * @var list<string>
     */
    private const CSV_HEADERS = ['Prefix', 'Description'];

    /**
     * @var array<string, string>
     */
    private const CSV_ALIASES = [
        'prefix' => 'prefix',
        'description' => 'description',
    ];

    public function __construct(private PrefixRepository $prefixes) {}

    /**
     * @return array{data: mixed, meta: array<string, int>}
     */
    public function list(string $search, mixed $perPage, string $sort = '', string $dir = 'asc'): array
    {
        $pageSize = CrudList::perPage($perPage);
        if ($pageSize === 'all') {
            return CrudList::fromCollection($this->prefixes->getForList($search, $sort, $dir));
        }

        return CrudList::fromPaginator($this->prefixes->paginateForList($search, $pageSize, $sort, $dir));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated): Prefix
    {
        return $this->prefixes->create($this->attributesFromRequest($validated));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Prefix $prefix, array $validated): Prefix
    {
        return $this->prefixes->save($prefix, $this->attributesFromRequest($validated));
    }

    public function delete(Prefix $prefix): void
    {
        $this->prefixes->delete($prefix);
    }

    public function template(): StreamedResponse
    {
        return Csv::download('prefix-template.csv', [self::CSV_HEADERS]);
    }

    public function export(string $search, string $sort = '', string $dir = 'asc'): StreamedResponse
    {
        $rows = [self::CSV_HEADERS];
        foreach ($this->prefixes->getForList($search, $sort, $dir) as $prefix) {
            $rows[] = [
                (string) ($prefix->prefix ?? ''),
                (string) ($prefix->description ?? ''),
            ];
        }

        return Csv::download('prefixes.csv', $rows);
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
        if (! isset($map['prefix']) || ! isset($map['description'])) {
            return ['message' => 'Invalid template. Required columns: Prefix, Description.', 'status' => 422];
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
                'prefix' => Csv::cell($row, $map, 'prefix'),
                'description' => Csv::cell($row, $map, 'description'),
            ];

            $validator = Validator::make(
                $payload,
                (new StorePrefixRequest)->rules(),
                (new StorePrefixRequest)->messages()
            );
            if ($validator->fails()) {
                $errors[] = [
                    'line' => $line,
                    'code' => $payload['prefix'],
                    'message' => collect($validator->errors()->all())->implode(' '),
                ];

                continue;
            }

            try {
                $this->prefixes->create($this->attributesFromRequest($validator->validated()));
                $imported++;
            } catch (Throwable) {
                $errors[] = [
                    'line' => $line,
                    'code' => $payload['prefix'],
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
            'prefix' => Text::clip($validated['prefix'] ?? '', 20),
            'description' => Text::clip($validated['description'] ?? '', 50),
        ];
    }
}
