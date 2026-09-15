<?php

namespace App\Services;

use App\Http\Requests\StoreDestinationRequest;
use App\Models\Destination;
use App\Repositories\CodeCascadeRepository;
use App\Repositories\DestinationRepository;
use App\Support\CrudList;
use App\Support\Csv;
use App\Support\Text;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DestinationService
{
    /**
     * @var list<string>
     */
    private const CSV_HEADERS = ['Code', 'Description'];

    /**
     * @var array<string, string>
     */
    private const CSV_ALIASES = [
        'code' => 'dstcde',
        'dstcde' => 'dstcde',
        'description' => 'dstdsc',
        'dstdsc' => 'dstdsc',
    ];

    public function __construct(
        private DestinationRepository $destinations,
        private CodeCascadeRepository $cascade,
    ) {}

    /**
     * @return array{data: mixed, meta: array<string, int>}
     */
    public function list(string $search, mixed $perPage, string $sort = '', string $dir = 'asc'): array
    {
        $pageSize = CrudList::perPage($perPage);
        if ($pageSize === 'all') {
            return CrudList::fromCollection($this->destinations->getForList($search, $sort, $dir));
        }

        return CrudList::fromPaginator($this->destinations->paginateForList($search, $pageSize, $sort, $dir));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated): Destination
    {
        return $this->destinations->create($this->attributesFromRequest($validated));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Destination $destination, array $validated): Destination
    {
        $oldCode = (string) ($destination->dstcde ?? '');
        $oldName = (string) ($destination->dstdsc ?? '');
        $this->destinations->save($destination, $this->attributesFromRequest($validated));
        $this->cascadeIfChanged(
            $oldCode,
            $oldName,
            (string) $destination->dstcde,
            (string) $destination->dstdsc,
        );

        return $destination;
    }

    public function delete(Destination $destination): void
    {
        $this->destinations->delete($destination);
    }

    public function template(): StreamedResponse
    {
        return Csv::download('destination-template.csv', [self::CSV_HEADERS]);
    }

    public function export(string $search, string $sort = '', string $dir = 'asc'): StreamedResponse
    {
        $rows = [self::CSV_HEADERS];
        foreach ($this->destinations->getForList($search, $sort, $dir) as $destination) {
            $rows[] = [
                (string) ($destination->dstcde ?? ''),
                (string) ($destination->dstdsc ?? ''),
            ];
        }

        return Csv::download('destinations.csv', $rows);
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
        if (! isset($map['dstcde']) || ! isset($map['dstdsc'])) {
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
                'dstcde' => Csv::cell($row, $map, 'dstcde'),
                'dstdsc' => Csv::cell($row, $map, 'dstdsc'),
            ];

            $validator = Validator::make(
                $payload,
                (new StoreDestinationRequest)->rules(),
                (new StoreDestinationRequest)->messages()
            );
            if ($validator->fails()) {
                $errors[] = [
                    'line' => $line,
                    'code' => $payload['dstcde'],
                    'message' => collect($validator->errors()->all())->implode(' '),
                ];

                continue;
            }

            try {
                $this->destinations->create($this->attributesFromRequest($validator->validated()));
                $imported++;
            } catch (Throwable) {
                $errors[] = [
                    'line' => $line,
                    'code' => $payload['dstcde'],
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
            'dstcde' => Text::clip($validated['dstcde'] ?? '', 25),
            'dstdsc' => Text::clip($validated['dstdsc'] ?? '', 100),
        ];
    }

    private function cascadeIfChanged(string $oldCode, string $oldName, string $newCode, string $newName): void
    {
        if ($oldCode === '' || ($oldCode === $newCode && $oldName === $newName)) {
            return;
        }

        $this->cascade->cascade('destinationfile', 'dstcde', 'dstdsc', $oldCode, $newCode, $newName);
    }
}
