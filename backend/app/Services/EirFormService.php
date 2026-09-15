<?php

namespace App\Services;

use App\Models\EirForm;
use App\Models\EirFormSketch;
use App\Models\User;
use App\Repositories\EirFormRepository;
use App\Support\CrudList;
use App\Support\EirSignature;
use App\Support\MenuPermission;
use App\Support\Text;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EirFormService
{
    private const MENPRG = 'view_equip_interchange_receipt.php';

    public const SKETCH_PARTS = [
        'flooring' => 'Flooring',
        'doorheader' => 'Door Header',
        'doorcamkeeper' => 'Door Cam Keeper',
        'hinges' => 'Hinges',
        'lookingbar' => 'Looking Bar',
        'lookingbarguide' => 'Looking Bar Guide',
        'lookingbarhandle' => 'Looking Bar Handle',
        'rearcornerpost' => 'Rear Corner Post',
        'lookinglatch' => 'Looking Latch',
        'toprail' => 'Top Rail',
        'frontheader' => 'Front Header',
        'frontcornerpost' => 'Front Corner Post',
        'forkliftassy' => 'Fork Lift assy',
        'crossmember' => 'Cross-member',
    ];

    public function __construct(private EirFormRepository $eirs) {}

    /**
     * @return array{
     *     destinations: list<array{dstcde: string, dstdsc: string}>,
     *     containers: list<string>,
     *     shippers: list<array{cuscde: string, cusdsc: string}>,
     *     consignees: list<array{concde: string, condsc: string}>,
     *     sketch_parts: array<string, string>,
     *     permissions: array{can_add: bool, can_edit: bool, can_delete: bool, can_view: bool, can_print: bool, can_accept: bool}
     * }
     */
    public function lookups(User $user): array
    {
        return [
            'destinations' => $this->eirs->destinations(),
            'containers' => $this->eirs->containers(),
            'shippers' => $this->eirs->shippers(),
            'consignees' => $this->eirs->consignees(),
            'sketch_parts' => self::SKETCH_PARTS,
            'permissions' => $this->permissionsFor($user),
        ];
    }

    /**
     * @return array{data: mixed, meta: array<string, int>}
     */
    public function list(string $search, mixed $perPage, string $sort = '', string $dir = 'asc'): array
    {
        $pageSize = CrudList::perPage($perPage);
        if ($pageSize === 'all') {
            return CrudList::fromCollection($this->eirs->getForList($search, $sort, $dir));
        }

        return CrudList::fromPaginator($this->eirs->paginateForList($search, $pageSize, $sort, $dir));
    }

    /**
     * @return array<string, mixed>
     */
    public function show(EirForm $eir): array
    {
        return $this->detail($eir);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function create(array $validated, User $user): array
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_ADD);

        return DB::transaction(function () use ($validated) {
            $docnum = Text::clip($this->eirs->nextDocnum(), 20);
            if ($docnum === '') {
                throw ValidationException::withMessages(['docnum' => ['Missing EIR No.']]);
            }

            $this->assertUniqueMove($validated, $docnum);
            $attributes = $this->headerFromRequest($validated, $docnum, true);
            $attributes = $this->storeSignatures($attributes, $validated, $docnum, null);
            $eir = $this->eirs->create($attributes);
            $this->eirs->replaceSketch($docnum, $this->sketchFromRequest($validated, $docnum));

            return $this->detail($eir);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function update(EirForm $eir, array $validated, User $user): array
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_EDIT);

        $docnum = trim((string) ($eir->docnum ?? ''));
        $this->assertUniqueMove($validated, $docnum);

        return DB::transaction(function () use ($eir, $validated, $docnum) {
            $attributes = $this->headerFromRequest($validated, $docnum, false);
            $attributes = $this->storeSignatures($attributes, $validated, $docnum, $eir);
            $eir = $this->eirs->save($eir, $attributes);
            $this->eirs->replaceSketch($docnum, $this->sketchFromRequest($validated, $docnum));

            return $this->detail($eir);
        });
    }

    public function delete(EirForm $eir, User $user): void
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_DELETE);

        if ($eir->isTagged()) {
            throw ValidationException::withMessages([
                'billing_no' => ['This EIR Document was already tagged at Billing Document No. : '.$eir->billing_no.'. Can not be Delete'],
            ]);
        }

        $docnum = trim((string) ($eir->docnum ?? ''));
        $this->deleteStoredSignature($eir->rep_driver_sign ?? '');
        $this->deleteStoredSignature($eir->checker_sign ?? '');
        $this->eirs->deleteByDocnum($docnum);
    }

    public function containerSize(string $vannum): string
    {
        return $this->eirs->containerSize($vannum) ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    public function accept(EirForm $eir, string $date, string $hour, string $minute, string $ampm, User $user): array
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_EDIT);

        if ($eir->isAccepted()) {
            throw ValidationException::withMessages([
                'accpt_dte' => ['This EIR Document was already accepted on '.$eir->accpt_dte],
            ]);
        }

        $stamp = $this->acceptanceStamp($date, $hour, $minute, $ampm);
        if ($stamp === '') {
            throw ValidationException::withMessages([
                'accpt_dte' => ['Acceptance date/time is required.'],
            ]);
        }

        $eir = $this->eirs->save($eir, ['accpt_dte' => $stamp]);

        return $this->detail($eir);
    }

    /**
     * @return array<string, mixed>
     */
    public function pdfPayload(EirForm $eir): array
    {
        if ($eir->isPrinted()) {
            throw ValidationException::withMessages([
                'is_print' => ['Please contact admin for re-printing!'],
            ]);
        }

        $eir->is_print = '1';
        $eir->save();

        $names = $this->eirs->printNames($eir);
        $remarks = [];
        foreach ($this->eirs->sketch(trim((string) $eir->docnum)) as $row) {
            $codes = [];
            foreach (EirFormSketch::FLAGS as $flag) {
                if ($flag === 'OK') {
                    continue;
                }
                if ((int) ($row->getAttribute($flag) ?? 0) === 1) {
                    $codes[] = $flag;
                }
            }
            if ($codes !== []) {
                $remarks[] = [
                    'desc' => trim((string) ($row->cont_sketch_desc ?? '')),
                    'codes' => implode(', ', $codes),
                ];
            }
        }

        $pickup = trim((string) ($eir->getAttribute('pickup') ?? ''));
        $return = trim((string) ($eir->getAttribute('return') ?? ''));
        $move = trim(($pickup !== '' ? 'Pickup : '.$pickup.' ' : '').($return !== '' ? 'Return : '.$return : ''));

        return [
            'company' => $this->eirs->company(),
            'docnum' => trim((string) ($eir->docnum ?? '')),
            'billing_no' => trim((string) ($eir->billing_no ?? '')),
            'vannum' => trim((string) ($eir->vannum ?? '')),
            'origin' => trim((string) ($eir->origin ?? '')),
            'type' => trim((string) ($eir->type ?? '')),
            'weight' => trim((string) ($eir->weight ?? '')),
            'voynum' => trim((string) ($eir->voynum ?? '')),
            'cusdsc' => $names['cusdsc'],
            'condsc' => $names['condsc'],
            'trucker' => trim((string) ($eir->trucker ?? '')),
            'driver_name' => trim((string) ($eir->driver_name ?? '')),
            'move' => $move,
            'dstdsc' => $names['dstdsc'],
            'special_ins' => trim((string) ($eir->special_ins ?? '')),
            'issue_dte' => $this->displayDate($eir->issue_dte),
            'issue_time' => substr(trim((string) ($eir->issue_time ?? '')), 0, 8),
            'accpt_dte' => $this->displayDateTime($eir->accpt_dte),
            'size' => trim((string) ($eir->size ?? '')),
            'seal_no' => trim((string) ($eir->seal_no ?? '')),
            'plateno' => trim((string) ($eir->plateno ?? '')),
            'move_dte' => $this->displayDate($eir->move_dte),
            'move_time' => substr(trim((string) ($eir->move_time ?? '')), 0, 8),
            'van_received_by' => strtoupper(trim((string) ($eir->van_received_by ?? ''))),
            'van_released_by' => strtoupper(trim((string) ($eir->van_released_by ?? ''))),
            'printed_at' => now('Asia/Manila')->format('F d, Y'),
            'remarks' => $remarks,
        ];
    }

    public function signaturePath(EirForm $eir, string $kind): ?string
    {
        $filename = $kind === 'checker'
            ? trim((string) ($eir->checker_sign ?? ''))
            : trim((string) ($eir->rep_driver_sign ?? ''));

        return $this->resolveSignaturePath($filename);
    }

    /**
     * @return array{can_add: bool, can_edit: bool, can_delete: bool, can_view: bool, can_print: bool, can_accept: bool}
     */
    private function permissionsFor(User $user): array
    {
        if ($user->isAdmin()) {
            return [
                'can_add' => true,
                'can_edit' => true,
                'can_delete' => true,
                'can_view' => true,
                'can_print' => true,
                'can_accept' => true,
            ];
        }

        $perms = $this->eirs->menuPermissions((string) $user->usrcde);
        if ($perms === null) {
            return [
                'can_add' => false,
                'can_edit' => false,
                'can_delete' => false,
                'can_view' => true,
                'can_print' => false,
                'can_accept' => false,
            ];
        }

        return [
            'can_add' => $perms['allow_add'],
            'can_edit' => $perms['allow_edit'],
            'can_delete' => $perms['allow_delete'],
            'can_view' => $perms['allow_view'],
            'can_print' => $perms['allow_print'],
            'can_accept' => $perms['allow_edit'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertUniqueMove(array $validated, string $docnum): void
    {
        $vannum = Text::clip($validated['vannum'] ?? '', 20);
        $pickup = strtoupper(Text::clip($validated['pickup'] ?? '', 20));
        $return = strtoupper(Text::clip($validated['return'] ?? '', 20));
        $field = $pickup !== '' ? 'pickup' : ($return !== '' ? 'return' : '');
        if ($vannum === '' || $field === '') {
            return;
        }

        $existing = $this->eirs->duplicateMoveDocnum($vannum, $field, $docnum);
        if ($existing !== null) {
            throw ValidationException::withMessages([
                'vannum' => ['Container number '.$vannum.' was already used with this type of move (EIR: '.$existing.')'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function headerFromRequest(array $validated, string $docnum, bool $creating): array
    {
        $pickup = strtoupper(Text::clip($validated['pickup'] ?? '', 20));
        $return = strtoupper(Text::clip($validated['return'] ?? '', 20));
        if ($pickup !== '') {
            $return = '';
        }

        $now = now('Asia/Manila');
        $issueDate = $creating ? $now->format('Y-m-d') : $this->datePart($validated['issue_dte'] ?? $now->format('Y-m-d'));
        $issueTime = $creating ? $now->format('H:i:s') : $this->timePart($validated['issue_time'] ?? $now->format('H:i:s'));

        return [
            'docnum' => $docnum,
            'booking_no' => Text::clip($validated['booking_no'] ?? '', 20),
            'origin' => Text::clip($validated['origin'] ?? '', 30),
            'vannum' => Text::clip($validated['vannum'] ?? '', 20),
            'type' => Text::clip($validated['type'] ?? '', 50),
            'size' => Text::clip($validated['size'] ?? '', 20),
            'weight' => Text::clip($validated['weight'] ?? '', 20),
            'seal_no' => Text::clip($validated['seal_no'] ?? '', 20),
            'voynum' => Text::clip($validated['voynum'] ?? '', 30),
            'cuscde' => Text::clip($validated['cuscde'] ?? '', 100),
            'concde' => Text::clip($validated['concde'] ?? '', 50),
            'issue_dte' => $issueDate !== '' ? $issueDate : null,
            'issue_time' => $issueTime !== '' ? $issueTime : null,
            'trucker' => Text::clip($validated['trucker'] ?? '', 100),
            'driver_name' => Text::clip($validated['driver_name'] ?? '', 100),
            'plateno' => Text::clip($validated['plateno'] ?? '', 50),
            'dstcde' => Text::clip($validated['dstcde'] ?? '', 50),
            'pickup' => $pickup,
            'return' => $return,
            'special_ins' => Text::clip($validated['special_ins'] ?? '', 300),
            'move_dte' => ($move = $this->datePart($validated['move_dte'] ?? '')) !== '' ? $move : null,
            'move_time' => ($mtime = $this->timePart($validated['move_time'] ?? '')) !== '' ? $mtime : null,
            'van_received_by' => Text::clip($validated['van_received_by'] ?? '', 100),
            'van_released_by' => Text::clip($validated['van_released_by'] ?? '', 100),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function storeSignatures(array $attributes, array $validated, string $docnum, ?EirForm $existing): array
    {
        $driver = $validated['driver_sign'] ?? null;
        if ($driver instanceof UploadedFile) {
            $this->assertOpaqueImage($driver, 'Shipper Representative / Driver Signature');
            $name = 'shpdriv_'.$docnum.'_'.now('Asia/Manila')->format('Ymd_His').'.'.$driver->getClientOriginalExtension();
            $driver->storeAs('eir-uploads', $name);
            if ($existing !== null) {
                $this->deleteStoredSignature((string) ($existing->rep_driver_sign ?? ''));
            }
            $attributes['rep_driver_sign'] = Text::clip($name, 100);
        } elseif ($existing === null) {
            // Avoid the legacy varchar default of the text "NULL".
            $attributes['rep_driver_sign'] = '';
        }

        $checker = $validated['checker_sign'] ?? null;
        if ($checker instanceof UploadedFile) {
            $this->assertOpaqueImage($checker, 'Checker Signature');
            $name = 'chker_'.$docnum.'_'.now('Asia/Manila')->format('Ymd_His').'.'.$checker->getClientOriginalExtension();
            $checker->storeAs('eir-uploads', $name);
            if ($existing !== null) {
                $this->deleteStoredSignature((string) ($existing->checker_sign ?? ''));
            }
            $attributes['checker_sign'] = Text::clip($name, 100);
        } elseif ($existing === null) {
            $attributes['checker_sign'] = '';
        }

        return $attributes;
    }

    private function assertOpaqueImage(UploadedFile $file, string $label): void
    {
        $contents = (string) file_get_contents($file->getRealPath() ?: '');
        if (stripos($contents, 'PLTE') !== false && stripos($contents, 'tRNS') !== false) {
            throw ValidationException::withMessages([
                'driver_sign' => [$label." :\nThe image has no background, please select image signature with background"],
            ]);
        }
    }

    private function deleteStoredSignature(string $filename): void
    {
        $filename = EirSignature::normalize($filename);
        if ($filename === '') {
            return;
        }
        $path = storage_path('app/eir-uploads/'.$filename);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function resolveSignaturePath(string $filename): ?string
    {
        $filename = EirSignature::normalize($filename);
        if ($filename === '' || str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            return null;
        }

        $stored = storage_path('app/eir-uploads/'.$filename);
        if (is_file($stored)) {
            return $stored;
        }

        $legacy = 'C:\\Users\\lstvuser\\Desktop\\webmoreta\\uploads\\'.$filename;
        if (is_file($legacy)) {
            return $legacy;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<array<string, mixed>>
     */
    private function sketchFromRequest(array $validated, string $docnum): array
    {
        $posted = is_array($validated['sketch'] ?? null) ? $validated['sketch'] : [];
        $rows = [];
        foreach (self::SKETCH_PARTS as $key => $desc) {
            $flags = is_array($posted[$key] ?? null) ? $posted[$key] : [];
            $row = [
                'docnum' => $docnum,
                'cont_sketch_desc' => $desc,
            ];
            foreach (EirFormSketch::FLAGS as $flag) {
                $row[$flag] = ! empty($flags[strtolower($flag)]) ? 1 : 0;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(EirForm $eir): array
    {
        $byDesc = [];
        foreach ($this->eirs->sketch(trim((string) $eir->docnum)) as $row) {
            $byDesc[trim((string) $row->cont_sketch_desc)] = $row->toSketchArray()['flags'];
        }

        $sketch = [];
        foreach (self::SKETCH_PARTS as $key => $desc) {
            $sketch[$key] = $byDesc[$desc] ?? array_fill_keys(array_map('strtolower', EirFormSketch::FLAGS), false);
        }

        return $eir->toDetailArray($sketch);
    }

    private function acceptanceStamp(string $date, string $hour, string $minute, string $ampm): string
    {
        $date = $this->datePart($date);
        if ($date === '') {
            return '';
        }
        $hour = str_pad(preg_replace('/\D/', '', $hour) ?: '0', 2, '0', STR_PAD_LEFT);
        $minute = str_pad(preg_replace('/\D/', '', $minute) ?: '0', 2, '0', STR_PAD_LEFT);
        $ampm = strtoupper(trim($ampm)) === 'PM' ? 'PM' : 'AM';
        $parsed = strtotime($date.' '.$hour.':'.$minute.' '.$ampm);

        return $parsed ? date('Y-m-d H:i:s', $parsed) : '';
    }

    private function datePart(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return '';
        }

        return substr($text, 0, 10);
    }

    private function timePart(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return '';
        }
        if (strlen($text) === 5) {
            return $text.':00';
        }

        return substr($text, 0, 8);
    }

    private function dateTimeString(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        $text = trim((string) ($value ?? ''));
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return '';
        }

        return substr($text, 0, 19);
    }

    private function displayDate(mixed $value): string
    {
        $date = $this->datePart($value);
        if ($date === '') {
            return '';
        }
        $parsed = strtotime($date);

        return $parsed ? date('m-d-Y', $parsed) : $date;
    }

    private function displayDateTime(mixed $value): string
    {
        $text = $this->dateTimeString($value);
        if ($text === '') {
            return '';
        }
        $parsed = strtotime($text);

        return $parsed ? date('m-d-Y H:i:s', $parsed) : $text;
    }
}
