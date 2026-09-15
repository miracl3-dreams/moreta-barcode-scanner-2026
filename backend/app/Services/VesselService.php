<?php

namespace App\Services;

use App\Http\Requests\StoreVesselRequest;
use App\Models\Vessel;
use App\Repositories\CodeCascadeRepository;
use App\Repositories\VesselRepository;
use App\Support\CrudList;
use App\Support\Csv;
use App\Support\Text;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class VesselService
{
    /**
     * @var list<string>
     */
    private const CSV_HEADERS = ['Code', 'Description'];

    /**
     * @var array<string, string>
     */
    private const CSV_ALIASES = [
        'code' => 'vsslcde',
        'vsslcde' => 'vsslcde',
        'description' => 'vssldsc',
        'vssldsc' => 'vssldsc',
    ];

    public function __construct(
        private VesselRepository $vessels,
        private CodeCascadeRepository $cascade,
    ) {}

    /**
     * @return array{data: mixed, meta: array<string, int>}
     */
    public function list(string $search, mixed $perPage, string $sort = '', string $dir = 'asc'): array
    {
        $pageSize = CrudList::perPage($perPage);
        if ($pageSize === 'all') {
            return CrudList::fromCollection($this->vessels->getForList($search, $sort, $dir));
        }

        return CrudList::fromPaginator($this->vessels->paginateForList($search, $pageSize, $sort, $dir));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated): Vessel
    {
        return $this->vessels->create($this->attributesFromRequest($validated));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Vessel $vessel, array $validated): Vessel
    {
        $oldCode = (string) ($vessel->vsslcde ?? '');
        $oldName = (string) ($vessel->vssldsc ?? '');
        $this->vessels->save($vessel, $this->attributesFromRequest($validated));
        $this->cascadeIfChanged(
            $oldCode,
            $oldName,
            (string) $vessel->vsslcde,
            (string) $vessel->vssldsc,
        );

        return $vessel;
    }

    public function delete(Vessel $vessel): void
    {
        $this->vessels->delete($vessel);
    }

    public function template(): StreamedResponse
    {
        return Csv::download('vessel-template.csv', [self::CSV_HEADERS]);
    }

    public function export(string $search, string $sort = '', string $dir = 'asc'): StreamedResponse
    {
        $rows = [self::CSV_HEADERS];
        foreach ($this->vessels->getForList($search, $sort, $dir) as $vessel) {
            $rows[] = [
                (string) ($vessel->vsslcde ?? ''),
                (string) ($vessel->vssldsc ?? ''),
            ];
        }

        return Csv::download('vessels.csv', $rows);
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
        if (! isset($map['vsslcde']) || ! isset($map['vssldsc'])) {
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
                'vsslcde' => Csv::cell($row, $map, 'vsslcde'),
                'vssldsc' => Csv::cell($row, $map, 'vssldsc'),
            ];

            $validator = Validator::make(
                $payload,
                (new StoreVesselRequest)->rules(),
                (new StoreVesselRequest)->messages()
            );
            if ($validator->fails()) {
                $errors[] = [
                    'line' => $line,
                    'code' => $payload['vsslcde'],
                    'message' => collect($validator->errors()->all())->implode(' '),
                ];

                continue;
            }

            try {
                $this->vessels->create($this->attributesFromRequest($validator->validated()));
                $imported++;
            } catch (Throwable) {
                $errors[] = [
                    'line' => $line,
                    'code' => $payload['vsslcde'],
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
            'vsslcde' => Text::clip($validated['vsslcde'] ?? '', 30),
            'vssldsc' => Text::clip($validated['vssldsc'] ?? '', 150),
        ];
    }

    private function cascadeIfChanged(string $oldCode, string $oldName, string $newCode, string $newName): void
    {
        if ($oldCode === '' || ($oldCode === $newCode && $oldName === $newName)) {
            return;
        }

        $this->cascade->cascade('vesselfile', 'vsslcde', 'vssldsc', $oldCode, $newCode, $newName);
    }
}
