<?php

namespace App\Services;

use App\Http\Requests\ContainerRules;
use App\Models\Container;
use App\Repositories\ContainerRepository;
use App\Support\CrudList;
use App\Support\Csv;
use App\Support\Text;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ContainerService
{
    /**
     * @var list<string>
     */
    private const CSV_HEADERS = [
        'Prefix',
        'Van #',
        'Description',
        'Type',
        'Supplier',
        'Date Acquired',
        'Months to Pay',
        'Start of Lease',
        'End of Lease',
        'Contract No.',
        'Contract Date',
        'Previous Van No.',
        'Date Fabricated',
        'Job Order No.',
        'Date Scrapped',
        'Buyer',
        'Date Sold',
        'Start Rental Date',
        'End Rental Date',
        'Remove',
        'Remarks',
        'Last Location',
    ];

    /**
     * @var array<string, string>
     */
    private const CSV_ALIASES = [
        'prefix' => 'prefix',
        'van #' => 'vannum',
        'van#' => 'vannum',
        'vannum' => 'vannum',
        'description' => 'vandsc',
        'vandsc' => 'vandsc',
        'type' => 'vantype',
        'vantype' => 'vantype',
        'supplier' => 'supplier',
        'date acquired' => 'acqdte',
        'acqdte' => 'acqdte',
        'months to pay' => 'montopay',
        'month/s to pay' => 'montopay',
        'montopay' => 'montopay',
        'start of lease' => 'start_lease',
        'start_lease' => 'start_lease',
        'end of lease' => 'end_lease',
        'end_lease' => 'end_lease',
        'contract no.' => 'contractno',
        'contract no' => 'contractno',
        'contractno' => 'contractno',
        'contract date' => 'contractdate',
        'contract date.' => 'contractdate',
        'contractdate' => 'contractdate',
        'previous van no.' => 'prevannum',
        'previous van no' => 'prevannum',
        'prevannum' => 'prevannum',
        'date fabricated' => 'date_fabricated',
        'date_fabricated' => 'date_fabricated',
        'job order no.' => 'joborderno',
        'job order no' => 'joborderno',
        'joborderno' => 'joborderno',
        'date scrapped' => 'date_scrapped',
        'date_scrapped' => 'date_scrapped',
        'buyer' => 'buyer',
        'date sold' => 'date_sold',
        'date_sold' => 'date_sold',
        'start rental date' => 'start_rentaldate',
        'start_rentaldate' => 'start_rentaldate',
        'end rental date' => 'end_rentaldate',
        'end_rentaldate' => 'end_rentaldate',
        'remove' => 'remove',
        'remarks' => 'remarks',
        'last location' => 'lastloc',
        'lastloc' => 'lastloc',
    ];

    public function __construct(private ContainerRepository $containers) {}

    /**
     * @return array{
     *     prefixes: list<string>,
     *     descriptions: list<string>,
     *     types: list<string>,
     *     destinations: list<array{dstcde: string, dstdsc: string}>
     * }
     */
    public function lookups(): array
    {
        return $this->containers->lookups();
    }

    /**
     * @return array{data: mixed, meta: array<string, int>}
     */
    public function list(string $search, mixed $perPage, string $sort = '', string $dir = 'asc'): array
    {
        $pageSize = CrudList::perPage($perPage);
        if ($pageSize === 'all') {
            $rows = $this->containers->getForList($search, $sort, $dir);
            $this->attachLastlocLocks($rows);

            return CrudList::fromCollection($rows);
        }

        $paginator = $this->containers->paginateForList($search, $pageSize, $sort, $dir);
        $this->attachLastlocLocks($paginator->getCollection());

        return CrudList::fromPaginator($paginator);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated): Container
    {
        $type = trim((string) ($validated['vantype'] ?? ''));

        if ($type === 'Converted') {
            return DB::transaction(function () use ($validated) {
                $base = $this->attributesFromRequest($validated, true);
                $previous = Text::clip($validated['prevannum'] ?? '', 100);
                $first = null;

                foreach (['newvan0', 'newvan1'] as $key) {
                    $row = $base;
                    $row['vannum'] = Text::clip($validated[$key] ?? '', 30);
                    $row['vandsc'] = 'Containerized 10 Footer Van';
                    $row['prevannum'] = $previous;
                    $saved = $this->containers->create($row);
                    $first ??= $saved;
                }

                $this->containers->markConvertedByVanNumber($previous);

                if (! $first instanceof Container) {
                    throw new \RuntimeException('Unable to create converted vans.');
                }

                return $first;
            });
        }

        return $this->containers->create($this->attributesFromRequest($validated, true));
    }

    public function show(Container $container): array
    {
        return $this->toListArray($container);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Container $container, array $validated): Container
    {
        $locked = $this->containers->vanNumberUsedInBol((string) ($container->vannum ?? ''));
        $existingLastloc = (string) ($container->lastloc ?? '');
        $this->containers->save(
            $container,
            $this->attributesFromRequest($validated, false, $locked, $existingLastloc)
        );

        return $container;
    }

    public function delete(Container $container): void
    {
        $this->containers->delete($container);
    }

    public function template(): StreamedResponse
    {
        return Csv::download('container-template.csv', [self::CSV_HEADERS]);
    }

    public function export(string $search, string $sort = '', string $dir = 'asc'): StreamedResponse
    {
        $rows = [self::CSV_HEADERS];
        foreach ($this->containers->getForList($search, $sort, $dir) as $container) {
            $rows[] = $this->csvRow($container);
        }

        return Csv::download('containers.csv', $rows);
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
        if (! isset($map['vannum']) || ! isset($map['vantype'])) {
            return ['message' => 'Invalid template. Required columns: Van #, Type.', 'status' => 422];
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
                'vannum' => Csv::cell($row, $map, 'vannum'),
                'vandsc' => Csv::cell($row, $map, 'vandsc'),
                'vantype' => Csv::cell($row, $map, 'vantype'),
                'supplier' => Csv::cell($row, $map, 'supplier'),
                'acqdte' => Csv::cell($row, $map, 'acqdte'),
                'montopay' => Csv::cell($row, $map, 'montopay'),
                'start_lease' => Csv::cell($row, $map, 'start_lease'),
                'end_lease' => Csv::cell($row, $map, 'end_lease'),
                'contractno' => Csv::cell($row, $map, 'contractno'),
                'contractdate' => Csv::cell($row, $map, 'contractdate'),
                'prevannum' => Csv::cell($row, $map, 'prevannum'),
                'date_fabricated' => Csv::cell($row, $map, 'date_fabricated'),
                'joborderno' => Csv::cell($row, $map, 'joborderno'),
                'date_scrapped' => Csv::cell($row, $map, 'date_scrapped'),
                'buyer' => Csv::cell($row, $map, 'buyer'),
                'date_sold' => Csv::cell($row, $map, 'date_sold'),
                'start_rentaldate' => Csv::cell($row, $map, 'start_rentaldate'),
                'end_rentaldate' => Csv::cell($row, $map, 'end_rentaldate'),
                'remove' => Csv::cell($row, $map, 'remove'),
                'remarks' => Csv::cell($row, $map, 'remarks'),
                'lastloc' => Csv::cell($row, $map, 'lastloc'),
            ];

            $validator = Validator::make(
                $payload,
                ContainerRules::rules(false),
                ContainerRules::messages()
            );
            $validator->after(function ($inner) use ($payload) {
                ContainerRules::uniquePrefixVan($inner, new Request($payload));
            });
            if ($validator->fails()) {
                $errors[] = [
                    'line' => $line,
                    'code' => $payload['vannum'],
                    'message' => collect($validator->errors()->all())->implode(' '),
                ];

                continue;
            }

            try {
                $validated = $validator->validated();
                if (trim((string) ($validated['vantype'] ?? '')) === 'Converted') {
                    $errors[] = [
                        'line' => $line,
                        'code' => $payload['vannum'],
                        'message' => 'Converted vans must be added in the form so both new van numbers can be created.',
                    ];

                    continue;
                }

                $this->containers->create($this->attributesFromRequest($validated, true));
                $imported++;
            } catch (Throwable) {
                $errors[] = [
                    'line' => $line,
                    'code' => $payload['vannum'],
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
     * @return array<string, mixed>
     */
    public function toListArray(Container $container): array
    {
        $container->setAttribute(
            'lastloc_locked',
            $this->containers->vanNumberUsedInBol((string) ($container->vannum ?? ''))
        );

        return $container->toListArray();
    }

    /**
     * @param  Collection<int, Container>  $rows
     */
    private function attachLastlocLocks(Collection $rows): void
    {
        $used = $this->containers->usedVanNumbers($rows->pluck('vannum')->all());
        $locked = array_fill_keys($used, true);

        foreach ($rows as $row) {
            $row->setAttribute('lastloc_locked', isset($locked[(string) ($row->vannum ?? '')]));
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributesFromRequest(
        array $validated,
        bool $creating,
        bool $lockLastloc = false,
        string $existingLastloc = '',
    ): array {
        $type = trim((string) ($validated['vantype'] ?? ''));
        $prefix = Text::clip($validated['prefix'] ?? '', 20);
        $vannum = Text::clip($validated['vannum'] ?? '', 30);

        $data = [
            'prefix' => $prefix,
            'vannum' => $vannum,
            'vandsc' => Text::clip($validated['vandsc'] ?? '', 50),
            'vantype' => Text::clip($type, 30),
            'supplier' => Text::clip($validated['supplier'] ?? '', 150),
            'acqdte' => $this->dateOrNull($validated['acqdte'] ?? null),
            'montopay' => (float) ($validated['montopay'] ?? 0),
            'start_lease' => $this->dateOrNull($validated['start_lease'] ?? null),
            'end_lease' => $this->dateOrNull($validated['end_lease'] ?? null),
            'contractno' => Text::clip($validated['contractno'] ?? '', 50),
            'contractdate' => $this->dateOrNull($validated['contractdate'] ?? null),
            'prevannum' => Text::clip($prefix.$vannum, 100),
            'date_fabricated' => $this->dateOrNull($validated['date_fabricated'] ?? null),
            'joborderno' => Text::clip($validated['joborderno'] ?? '', 50),
            'date_scrapped' => $this->dateOrNull($validated['date_scrapped'] ?? null),
            'buyer' => Text::clip($validated['buyer'] ?? '', 50),
            'date_sold' => $this->dateOrNull($validated['date_sold'] ?? null),
            'start_rentaldate' => $this->dateOrNull($validated['start_rentaldate'] ?? null),
            'end_rentaldate' => $this->dateOrNull($validated['end_rentaldate'] ?? null),
            'remove' => $this->removeFlag($validated['remove'] ?? false),
            'remarks' => Text::clip($validated['remarks'] ?? '', 200),
            'lastloc' => Text::clip($validated['lastloc'] ?? '', 100),
        ];

        if ($lockLastloc) {
            $data['lastloc'] = $existingLastloc;
        }

        $upper = strtoupper($type);
        if ($upper === 'PURCHASED') {
            $data['montopay'] = 0;
        }
        if ($upper === 'CONVERTED') {
            $data['montopay'] = 0;
            $data['supplier'] = '';
            $data['vandsc'] = 'Containerized 10 Footer Van';
            $data['vantype'] = 'Converted';
            if ($creating) {
                $data['prevannum'] = Text::clip($validated['prevannum'] ?? '', 100);
            }
        }

        if ($creating) {
            $data['isFree'] = 1;
            $data['isConverted'] = 0;
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    private function csvRow(Container $container): array
    {
        $row = $container->toListArray();

        return [
            $row['prefix'],
            $row['vannum'],
            $row['vandsc'],
            $row['vantype'],
            $row['supplier'],
            $row['acqdte'],
            (string) $row['montopay'],
            $row['start_lease'],
            $row['end_lease'],
            $row['contractno'],
            $row['contractdate'],
            $row['prevannum'],
            $row['date_fabricated'],
            $row['joborderno'],
            $row['date_scrapped'],
            $row['buyer'],
            $row['date_sold'],
            $row['start_rentaldate'],
            $row['end_rentaldate'],
            $row['remove'] ? 'Y' : 'N',
            $row['remarks'],
            $row['lastloc'],
        ];
    }

    private function dateOrNull(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return null;
        }

        return substr($text, 0, 10);
    }

    private function removeFlag(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Y' : 'N';
        }

        $text = strtoupper(trim((string) $value));

        return in_array($text, ['Y', '1', 'TRUE', 'YES'], true) ? 'Y' : 'N';
    }
}
