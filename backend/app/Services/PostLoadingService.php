<?php

namespace App\Services;

use App\Repositories\PostLoadingRepository;
use App\Support\PostLoadingPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostLoadingService
{
    public function __construct(private PostLoadingRepository $report) {}

    /**
     * @return array{categories: list<array{catcde: string, catdsc: string}>}
     */
    public function lookups(): array
    {
        return [
            'categories' => $this->report->categoriesInBl(),
        ];
    }

    /**
     * @return list<array{
     *     docnum: string,
     *     catcde: string,
     *     vannum: string,
     *     reason: string,
     *     loadedon: string,
     *     status: string
     * }>
     */
    public function lines(string $voynum): array
    {
        $rows = [];
        foreach ($this->report->listLines(trim($voynum)) as $line) {
            $rows[] = [
                'docnum' => trim((string) ($line->docnum ?? '')),
                'catcde' => trim((string) ($line->catcde ?? '')),
                'vannum' => trim((string) ($line->vannum ?? '')),
                'reason' => trim((string) ($line->reason_notloaded ?? '')),
                'loadedon' => trim((string) ($line->loaded_onvoyage ?? '')),
                'status' => strtoupper(trim((string) ($line->post_loadingstatus ?? ''))),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{docnum: string, vannum?: string, reason?: string, loadedon?: string}>  $lines
     */
    public function update(string $voynum, array $lines): JsonResponse
    {
        $voynum = trim($voynum);
        DB::transaction(function () use ($voynum, $lines) {
            foreach ($lines as $line) {
                $reason = trim((string) ($line['reason'] ?? ''));
                $loaded = trim((string) ($line['loadedon'] ?? ''));
                $status = ($reason !== '' && $loaded === '') ? 'pending' : '';
                $this->report->updateLine(
                    $voynum,
                    trim((string) ($line['docnum'] ?? '')),
                    trim((string) ($line['vannum'] ?? '')),
                    [
                        'reason_notloaded' => $reason,
                        'loaded_onvoyage' => $loaded,
                        'post_loadingstatus' => $status,
                    ],
                );
            }
        });

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function pdf(array $filters): Response
    {
        $voynum = trim((string) ($filters['voynum'] ?? ''));
        $catcde = trim((string) ($filters['catcde'] ?? ''));
        $copy = strtolower(trim((string) ($filters['copyfor'] ?? '')));
        if ($voynum === '' || $catcde === '' || ! in_array($copy, ['office', 'checker'], true)) {
            throw ValidationException::withMessages([
                'copyfor' => 'Please fill up required fields!',
            ]);
        }

        $category = $this->report->category($catcde);
        $columns = self::columns($copy, (string) ($category['iscontainer'] ?? ''), $category['catcde'] ?? $catcde);
        if (isset($columns['error'])) {
            throw ValidationException::withMessages([
                'catcde' => $columns['error'],
            ]);
        }

        $lines = $this->report->printLines($voynum, $catcde);
        $shippers = $this->report->shipperNames(
            $lines->map(fn ($row) => trim((string) ($row->cuscde ?? '')))->all(),
        );
        $consignees = $this->report->consigneeNames(
            $lines->map(fn ($row) => trim((string) ($row->concde ?? '')))->all(),
        );
        $sailing = $this->report->sailing($voynum);

        $rows = [];
        $number = 1;
        foreach ($lines as $line) {
            $cells = [];
            foreach ($columns['fields'] as $field) {
                $cells[] = [
                    'field' => $field,
                    'value' => $this->cellValue($field, $line, $number, $shippers, $consignees),
                ];
            }
            $rows[] = $cells;
            $number++;
        }

        return new Response(
            PostLoadingPdf::make()->render([
                'company' => $this->report->companyName(),
                'title' => 'Post Loading Report ('.strtoupper($copy).')',
                'voynum' => $voynum,
                'catcde' => $catcde,
                'sailing_date' => $sailing['date'],
                'vessel' => $sailing['vessel'],
                'printed_at' => now('Asia/Manila')->format('F j, Y'),
                'headers' => $columns['headers'],
                'positions' => $columns['positions'],
                'rows' => $rows,
            ]),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="post-loading.pdf"',
            ],
        );
    }

    /**
     * @return array{error: string}|array{headers: list<string>, fields: list<string>, positions: list<int>}
     */
    public static function columns(string $copy, string $iscontainer, string $catcde): array
    {
        $cat = strtoupper(trim($catcde));
        $container = strtoupper(trim($iscontainer)) === 'Y';
        if ($copy === 'office') {
            if ($container) {
                return [
                    'headers' => ['No.', 'Van No.', 'Shipper', 'Consignee', 'Description', 'B/L #', 'Tons/CBM', 'Reason not Loaded', 'Loaded on what Voyage'],
                    'fields' => ['no', 'vannum', 'cuscde', 'concde', 'classdsc', 'docnum', 'weiamt', 'reason_notloaded', 'loaded_onvoyage'],
                    'positions' => [25, 40, 130, 250, 360, 530, 590, 670, 800],
                ];
            }
            if ($cat === 'HEAVY EQUIPMENT') {
                return [
                    'headers' => ['No.', 'Shipper', 'Consignee', 'Description', 'B/L #', 'Tons/CBM', 'Reason not Loaded', 'Loaded on what Voyage'],
                    'fields' => ['no', 'cuscde', 'concde', 'classdsc', 'docnum', 'weiamt', 'reason_notloaded', 'loaded_onvoyage'],
                    'positions' => [25, 70, 200, 400, 530, 590, 670, 800],
                ];
            }
            if ($cat === 'LCL-IN CONTAINER') {
                return ['error' => 'Report not Applicable'];
            }
            if ($cat === 'LCL-NOT IN CONTAINER') {
                return [
                    'headers' => ['No.', 'Shipper', 'Consignee', 'Description', 'B/L #', 'Tons/CBM', 'Reason not Loaded', 'Loaded on what Voyage'],
                    'fields' => ['no', 'cuscde', 'concde', 'classdsc', 'docnum', 'weiamt', 'reason_notloaded', 'loaded_onvoyage'],
                    'positions' => [25, 70, 200, 400, 530, 590, 670, 800],
                ];
            }
            if ($cat === 'VEHICLES IN CONTAINER') {
                return [
                    'headers' => ['No.', 'Van No.', 'Shipper', 'Consignee', 'Description', 'Plate # / Conduction #', 'B/L #', 'Tons/CBM', 'Reason not Loaded', 'Loaded on what Voyage'],
                    'fields' => ['no', 'vannum', 'cuscde', 'concde', 'classdsc', 'plate', 'docnum', 'weiamt', 'reason_notloaded', 'loaded_onvoyage'],
                    'positions' => [25, 50, 150, 180, 250, 350, 480, 580, 650, 800],
                ];
            }
            if ($cat === 'VEHICLES NOT IN CONTAINERS') {
                return [
                    'headers' => ['No.', 'Shipper', 'Consignee', 'Description', 'Plate # / Conduction #', 'B/L #', 'Tons/CBM', 'Reason not Loaded', 'Loaded on what Voyage'],
                    'fields' => ['no', 'cuscde', 'concde', 'classdsc', 'plate', 'docnum', 'weiamt', 'reason_notloaded', 'loaded_onvoyage'],
                    'positions' => [25, 40, 90, 250, 400, 530, 590, 670, 800],
                ];
            }

            return [
                'headers' => ['No.', 'Van No.', 'Shipper', 'Consignee', 'Description', 'Plate # / Conduction #', 'B/L #', 'Tons/CBM', 'Reason not Loaded', 'Loaded on what Voyage'],
                'fields' => ['no', 'vannum', 'cuscde', 'concde', 'classdsc', 'plate', 'docnum', 'weiamt', 'reason_notloaded', 'loaded_onvoyage'],
                'positions' => [25, 50, 150, 180, 250, 350, 480, 580, 650, 800],
            ];
        }

        if ($container) {
            return [
                'headers' => ['No.', 'Van No.', 'Description', 'B/L #', 'Tons/CBM', 'Reason not Loaded', 'Loaded on what Voyage'],
                'fields' => ['no', 'vannum', 'classdsc', 'docnum', 'weiamt', 'reason_notloaded', 'loaded_onvoyage'],
                'positions' => [25, 60, 150, 320, 400, 600, 800],
            ];
        }
        if ($cat === 'HEAVY EQUIPMENT') {
            return [
                'headers' => ['No.', 'Description', 'B/L #', 'Tons/CBM', 'Reason not Loaded', 'Loaded on what Voyage'],
                'fields' => ['no', 'classdsc', 'docnum', 'weiamt', 'reason_notloaded', 'loaded_onvoyage'],
                'positions' => [25, 60, 150, 270, 600, 800],
            ];
        }
        if ($cat === 'LCL-IN CONTAINER') {
            return ['error' => 'Report not Applicable'];
        }
        if ($cat === 'LCL-NOT IN CONTAINER') {
            return [
                'headers' => ['No.', 'Description', 'B/L #', 'Tons/CBM', 'Reason not Loaded', 'Loaded on what Voyage'],
                'fields' => ['no', 'classdsc', 'docnum', 'weiamt', 'reason_notloaded', 'loaded_onvoyage'],
                'positions' => [25, 60, 150, 270, 600, 800],
            ];
        }
        if ($cat === 'VEHICLES IN CONTAINER') {
            return [
                'headers' => ['No.', 'Van No.', 'Description', 'Plate # / Conduction #', 'B/L #', 'Tons/CBM', 'Reason not Loaded', 'Loaded on what Voyage'],
                'fields' => ['no', 'vannum', 'classdsc', 'plate', 'docnum', 'weiamt', 'reason_notloaded', 'loaded_onvoyage'],
                'positions' => [25, 60, 150, 270, 400, 520, 600, 800],
            ];
        }
        if ($cat === 'VEHICLES NOT IN CONTAINERS') {
            return [
                'headers' => ['No.', 'Description', 'Plate # / Conduction #', 'B/L #', 'Tons/CBM', 'Reason not Loaded', 'Loaded on what Voyage'],
                'fields' => ['no', 'classdsc', 'plate', 'docnum', 'weiamt', 'reason_notloaded', 'loaded_onvoyage'],
                'positions' => [25, 60, 190, 350, 470, 600, 800],
            ];
        }

        return [
            'headers' => ['No.', 'Van No.', 'Description', 'Plate # / Conduction #', 'B/L #', 'Tons/CBM', 'Reason not Loaded', 'Loaded on what Voyage'],
            'fields' => ['no', 'vannum', 'classdsc', 'plate', 'docnum', 'weiamt', 'reason_notloaded', 'loaded_onvoyage'],
            'positions' => [25, 60, 150, 270, 400, 520, 600, 800],
        ];
    }

    /**
     * @param  array<string, string>  $shippers
     * @param  array<string, string>  $consignees
     */
    private function cellValue(string $field, object $line, int $number, array $shippers, array $consignees): string
    {
        if ($field === 'no') {
            return $number.'.';
        }
        if ($field === 'cuscde') {
            return substr($shippers[trim((string) ($line->cuscde ?? ''))] ?? '', 0, 18);
        }
        if ($field === 'concde') {
            return substr($consignees[trim((string) ($line->concde ?? ''))] ?? '', 0, 18);
        }
        if ($field === 'classdsc') {
            return substr(trim((string) ($line->classdsc ?? '')), 0, 30);
        }

        return trim((string) ($line->{$field} ?? ''));
    }
}
