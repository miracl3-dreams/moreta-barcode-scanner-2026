<?php

namespace App\Services;

use App\Models\EirFormSketch;
use App\Repositories\BarcodeScannerRepository;
use App\Support\Text;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BarcodeScannerService
{
    /** @var list<string> */
    private const MOVE_TYPES = ['pickup_mt', 'pickup_full', 'return_full', 'return_mt'];

    public const PICKUP_MT_TO_RETURN_MT_MESSAGE =
        'Cannot change Pickup MT to Return MT. This indicates a damaged container. Ask a supervisor to update the EIR status.';

    public const ALREADY_RETURNED_MESSAGE =
        'This EIR is already returned. It cannot be scanned again.';

    public static function destinationRequiresVoyage(string $dstcde, string $dstdsc = ''): bool
    {
        $haystack = strtoupper(preg_replace('/\s+/', ' ', trim($dstcde.' '.$dstdsc)) ?? '');

        return str_contains($haystack, 'LD TO VSL')
            || str_contains($haystack, 'LOADED TO VESSEL')
            || str_contains($haystack, 'LOAD TO VESSEL');
    }

    public function __construct(private BarcodeScannerRepository $scanner) {}

    /**
     * @return array{
     *     destinations: list<array{dstcde: string, dstdsc: string, requires_voyage: bool}>,
     *     voyages: list<array{voynum: string, trndte: string}>,
     *     sketch_parts: array<string, string>,
     *     sketch_groups: array<string, list<string>>
     * }
     */
    public function lookups(): array
    {
        return [
            'destinations' => array_map(
                fn (array $row) => [
                    'dstcde' => $row['dstcde'],
                    'dstdsc' => $row['dstdsc'],
                    'requires_voyage' => self::destinationRequiresVoyage($row['dstcde'], $row['dstdsc']),
                ],
                $this->scanner->destinations(),
            ),
            'voyages' => $this->scanner->activeVoyages(),
            'sketch_parts' => EirFormService::SKETCH_PARTS,
            'sketch_groups' => [
                'Interior' => ['flooring'],
                'Top Right Side' => [
                    'doorheader',
                    'doorcamkeeper',
                    'hinges',
                    'lookingbar',
                    'lookingbarguide',
                    'lookingbarhandle',
                    'rearcornerpost',
                    'lookinglatch',
                ],
                'Front Left Side' => [
                    'toprail',
                    'frontheader',
                    'frontcornerpost',
                    'forkliftassy',
                    'crossmember',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function scan(string $code): array
    {
        $docnum = Text::clip($code, 20);
        $header = $this->scanner->findHeaderByDocnum($docnum);
        if ($header === null) {
            throw ValidationException::withMessages([
                'code' => ['Invalid EIR no.'],
            ]);
        }

        if (self::isReturnedMove((string) ($header->return ?? ''))) {
            throw ValidationException::withMessages([
                'code' => [self::ALREADY_RETURNED_MESSAGE],
            ]);
        }

        return [
            'docnum' => trim((string) ($header->docnum ?? '')),
            'pickup' => trim((string) ($header->pickup ?? '')),
            'return' => trim((string) ($header->return ?? '')),
            'dstcde' => trim((string) ($header->dstcde ?? '')),
            'voynum' => trim((string) data_get($header, 'voynum', '')),
            'vannum' => trim((string) data_get($header, 'vannum', '')),
            'sketch' => $this->sketchByPart($docnum),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{ok: bool, message: string, docnum: string}
     */
    public function save(array $validated): array
    {
        $docnum = Text::clip($validated['code'] ?? '', 20);
        $header = $this->scanner->findHeaderByDocnum($docnum);
        if ($header === null) {
            throw ValidationException::withMessages([
                'code' => ['Invalid EIR no.'],
            ]);
        }

        if (self::isReturnedMove((string) ($header->return ?? ''))) {
            throw ValidationException::withMessages([
                'code' => [self::ALREADY_RETURNED_MESSAGE],
            ]);
        }

        $movetype = strtolower(trim((string) ($validated['movetype'] ?? '')));
        if (! in_array($movetype, self::MOVE_TYPES, true)) {
            throw ValidationException::withMessages([
                'movetype' => ['Type of Move is required.'],
            ]);
        }

        if ($this->isPickupMt($header) && $movetype === 'return_mt') {
            throw ValidationException::withMessages([
                'movetype' => [self::PICKUP_MT_TO_RETURN_MT_MESSAGE],
            ]);
        }

        $dstcde = Text::clip($validated['dstcde'] ?? '', 50);
        $dstdsc = $this->destinationDescription($dstcde);
        $voynum = Text::clip($validated['voynum'] ?? '', 30);
        $currentVoynum = trim((string) data_get($header, 'voynum', ''));
        $needsVoyage = self::destinationRequiresVoyage($dstcde, $dstdsc);
        $voyageAllowed = $voynum !== ''
            && ($this->scanner->voyageIsActive($voynum) || $voynum === $currentVoynum);

        if ($needsVoyage && ! $voyageAllowed) {
            throw ValidationException::withMessages([
                'voynum' => ['Voyage Number is required when Return Van To is Loaded to Vessel.'],
            ]);
        }

        // Optional for other destinations, but keep the voyage the checker picked.
        if (! $voyageAllowed) {
            $voynum = '';
        }

        [$movement, $status] = explode('_', $movetype, 2);
        $status = strtoupper($status);
        $vannum = trim((string) data_get($header, 'vannum', ''));
        $now = now('Asia/Manila')->format('Y-m-d H:i:s');
        $eirstatus = trim((string) data_get($header, 'eirstatus', ''));
        $previousDstcde = trim((string) data_get($header, 'dstcde', ''));
        $origin = $previousDstcde !== '' ? $previousDstcde : $this->scanner->vanLastloc($vannum);
        $user = Auth::user();
        $usrnam = Text::clip((string) data_get($user, 'usrname', data_get($user, 'usrcde', '')), 20);

        $attributes = [
            'dstcde' => $dstcde,
            'voynum' => $voynum,
        ];
        if ($movement === 'pickup') {
            $attributes['pickup'] = $status;
            $attributes['return'] = null;
        } else {
            $attributes['pickup'] = null;
            $attributes['return'] = $status;
        }

        if ($movement === 'return') {
            $attributes['eirstatus'] = 'Gate in Laden';
            $attributes['accpt_dte'] = $now;
            if ($eirstatus === '') {
                $attributes['eirstatusdte'] = $now;
            }
        } elseif ($eirstatus === '') {
            $attributes['eirstatus'] = 'Van out Empty';
            $attributes['eirstatusdte'] = $now;
        } else {
            $attributes['eirstatus'] = 'Gate in Laden';
        }

        // A re-scan that only assigns the voyage is still a move worth logging,
        // otherwise Van Endorsement never learns the van was returned for it.
        $moved = $vannum !== '' && $dstcde !== ''
            && ($dstcde !== $previousDstcde || $voynum !== $currentVoynum);
        $catcde = $moved ? $this->scanner->vanCategory($vannum) : '';
        $moveLabel = strtoupper($movement.' '.$status);

        DB::transaction(function () use ($docnum, $attributes, $validated, $vannum, $dstcde, $origin, $voynum, $now, $usrnam, $header, $moved, $catcde, $moveLabel) {
            $this->scanner->updateHeader($docnum, $attributes);
            $this->scanner->replaceSketch($docnum, $this->sketchFromRequest($validated, $docnum));
            $this->scanner->updateVanLastloc($vannum, $dstcde);

            if (! $moved) {
                return;
            }

            $this->scanner->insertLocationUsage([
                'docnum' => $docnum,
                'eir' => Text::clip($docnum, 20),
                'trncde' => 'BC',
                'trndte' => $now,
                'vannum' => $vannum,
                'origin' => Text::clip($origin, 30),
                'dstcde' => $dstcde,
                'voynum' => $voynum,
                'catcde' => Text::clip($catcde, 100),
                'vt_status' => Text::clip($moveLabel, 30),
                'cuscde' => Text::clip((string) data_get($header, 'cuscde', ''), 100),
                'concde' => Text::clip((string) data_get($header, 'concde', ''), 50),
                'usrnam' => $usrnam,
                'linenum' => 1,
                'adddte' => $now,
            ]);
        });

        return [
            'ok' => true,
            'message' => 'EIR details have been saved.',
            'docnum' => $docnum,
        ];
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function sketchByPart(string $docnum): array
    {
        $byDesc = [];
        foreach ($this->scanner->sketch($docnum) as $row) {
            $byDesc[trim((string) $row->cont_sketch_desc)] = $row->toSketchArray()['flags'];
        }

        $sketch = [];
        $empty = array_fill_keys(array_map('strtolower', EirFormSketch::FLAGS), false);
        foreach (EirFormService::SKETCH_PARTS as $key => $desc) {
            $sketch[$key] = $byDesc[$desc] ?? $empty;
        }

        return $sketch;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<array<string, mixed>>
     */
    private function sketchFromRequest(array $validated, string $docnum): array
    {
        $posted = is_array($validated['sketch'] ?? null) ? $validated['sketch'] : [];
        $rows = [];
        foreach (EirFormService::SKETCH_PARTS as $key => $desc) {
            $flags = is_array($posted[$key] ?? null) ? $posted[$key] : [];
            $selected = [];
            foreach (EirFormSketch::FLAGS as $flag) {
                if (! empty($flags[strtolower($flag)])) {
                    $selected[] = $flag;
                }
            }
            if (count($selected) !== 1) {
                throw ValidationException::withMessages([
                    'sketch' => ['Each part must have exactly one damage selected.'],
                ]);
            }

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

    private function destinationDescription(string $dstcde): string
    {
        foreach ($this->scanner->destinations() as $row) {
            if ($row['dstcde'] === $dstcde) {
                return $row['dstdsc'];
            }
        }

        return '';
    }

    public static function isReturnedMove(?string $return): bool
    {
        $value = strtoupper(trim((string) $return));

        return $value === 'FULL' || $value === 'MT';
    }

    private function isPickupMt(object $header): bool
    {
        return strtoupper(trim((string) ($header->pickup ?? ''))) === 'MT';
    }
}
