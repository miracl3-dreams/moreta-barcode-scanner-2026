<?php

namespace App\Services;

use App\Models\EirForm;
use App\Repositories\EirSigningRepository;
use App\Support\CrudList;
use App\Support\EirSignature;
use App\Support\Text;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EirSigningService
{
    public function __construct(private EirSigningRepository $signing) {}

    /**
     * @return array{data: mixed, meta: array<string, int>}
     */
    public function list(string $search, mixed $perPage, string $sort = '', string $dir = 'desc'): array
    {
        $pageSize = CrudList::perPage($perPage);
        if ($pageSize === 'all') {
            $rows = $this->signing->getPending($search, $sort, $dir)->map(fn (EirForm $row) => $this->toPendingArray($row));

            return [
                'data' => $rows->values(),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $rows->count(),
                    'total' => $rows->count(),
                ],
            ];
        }

        $paginator = $this->signing->paginatePending($search, $pageSize, $sort, $dir);

        return [
            'data' => $paginator->getCollection()->map(fn (EirForm $row) => $this->toPendingArray($row))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => max(1, $paginator->lastPage()),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(EirForm $eir): array
    {
        $this->assertPending($eir);

        return $this->toDetailArray($eir);
    }

    /**
     * @return array<string, mixed>
     */
    public function store(EirForm $eir, UploadedFile $file): array
    {
        return DB::transaction(function () use ($eir, $file) {
            $locked = $this->signing->lockByRecid((int) $eir->recid);
            if ($locked === null) {
                throw ValidationException::withMessages([
                    'signature' => ['EIR form not present.'],
                ]);
            }
            $this->assertPending($locked);
            $this->assertOpaqueImage($file);

            $docnum = Text::clip($locked->docnum ?? '', 20);
            $name = 'shpdriv_'.$docnum.'_'.now('Asia/Manila')->format('Ymd_His').'.png';
            $this->storeSignatureFile($file, $name);
            $saved = $this->signing->save($locked, [
                'rep_driver_sign' => Text::clip($name, 100),
            ]);

            return [
                'ok' => true,
                'docnum' => trim((string) ($saved->docnum ?? '')),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function toPendingArray(EirForm $eir): array
    {
        return [
            'recid' => (int) $eir->recid,
            'docnum' => trim((string) ($eir->docnum ?? '')),
            'issue_dte' => $this->dateString($eir->issue_dte),
            'vannum' => trim((string) ($eir->vannum ?? '')),
            'trucker' => trim((string) ($eir->trucker ?? '')),
            'origin' => trim((string) ($eir->origin ?? '')),
            'move_type' => $this->moveType($eir),
            'driver_name' => trim((string) ($eir->driver_name ?? '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetailArray(EirForm $eir): array
    {
        return [
            'recid' => (int) $eir->recid,
            'docnum' => trim((string) ($eir->docnum ?? '')),
            'issue_dte' => $this->dateString($eir->issue_dte),
            'vannum' => trim((string) ($eir->vannum ?? '')),
            'trucker' => trim((string) ($eir->trucker ?? '')),
            'origin' => trim((string) ($eir->origin ?? '')),
            'dstcde' => trim((string) ($eir->dstcde ?? '')),
            'driver_name' => trim((string) ($eir->driver_name ?? '')),
            'plateno' => trim((string) ($eir->plateno ?? '')),
            'type' => trim((string) ($eir->type ?? '')),
            'size' => trim((string) ($eir->size ?? '')),
            'move_type' => $this->moveType($eir),
            'seal_no' => trim((string) ($eir->seal_no ?? '')),
        ];
    }

    private function moveType(EirForm $eir): string
    {
        $pickup = strtoupper(trim((string) ($eir->getAttribute('pickup') ?? '')));
        $return = strtoupper(trim((string) ($eir->getAttribute('return') ?? '')));
        if ($pickup !== '') {
            return 'Pickup '.$pickup;
        }
        if ($return !== '') {
            return 'Return '.$return;
        }

        return '';
    }

    private function dateString(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return '';
        }

        return substr($text, 0, 10);
    }

    private function assertPending(EirForm $eir): void
    {
        if ($eir->isAccepted()) {
            throw ValidationException::withMessages([
                'signature' => ['This EIR is already accepted and is no longer available for signing.'],
            ]);
        }
        if (! EirSignature::isBlank($eir->rep_driver_sign ?? '')) {
            throw ValidationException::withMessages([
                'signature' => ['This EIR is already signed.'],
            ]);
        }
    }

    private function assertOpaqueImage(UploadedFile $file): void
    {
        $contents = (string) file_get_contents($file->getRealPath() ?: '');
        if (stripos($contents, 'PLTE') !== false && stripos($contents, 'tRNS') !== false) {
            throw ValidationException::withMessages([
                'signature' => ["Shipper Representative / Driver Signature :\nThe image has no background, please select image signature with background"],
            ]);
        }
    }

    private function storeSignatureFile(UploadedFile $file, string $name): void
    {
        $dir = $this->signatureDirectory();
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw ValidationException::withMessages([
                'signature' => ['Unable to store the signature file.'],
            ]);
        }
        $file->move($dir, $name);
    }

    private function signatureDirectory(): string
    {
        $custom = trim((string) env('EIR_UPLOADS_PATH', ''));
        if ($custom !== '') {
            return rtrim($custom, '/\\');
        }

        return storage_path('app/eir-uploads');
    }
}
