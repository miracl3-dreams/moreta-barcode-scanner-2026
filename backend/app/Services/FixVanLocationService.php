<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\FixVanLocationRepository;
use App\Repositories\UserActivityRepository;
use App\Support\Text;
use Illuminate\Validation\ValidationException;

class FixVanLocationService
{
    private const MODULE = 'POS';

    public function __construct(
        private FixVanLocationRepository $locations,
        private UserActivityRepository $activities,
    ) {}

    /**
     * @return array{destinations: list<array{value: string, label: string}>}
     */
    public function lookups(): array
    {
        return ['destinations' => $this->locations->destinations()];
    }

    /**
     * @param  array{docnum: string, vannum: string, lastloc: string}  $validated
     */
    public function replace(User $user, array $validated): void
    {
        $docnum = Text::clip($validated['docnum'] ?? '', 100);
        $vannum = Text::clip($validated['vannum'] ?? '', 30);
        $lastloc = Text::clip($validated['lastloc'] ?? '', 100);

        if ($docnum === '' && $vannum === '' && $lastloc === '') {
            throw ValidationException::withMessages(['docnum' => 'fill all info!']);
        }
        if ($docnum === '') {
            throw ValidationException::withMessages(['docnum' => 'fill docnum!']);
        }
        if ($vannum === '') {
            throw ValidationException::withMessages(['vannum' => 'fill vannum!']);
        }
        if ($lastloc === '') {
            throw ValidationException::withMessages(['lastloc' => 'fill branch!']);
        }

        $this->locations->updateVanLastloc($vannum, $lastloc);
        $historyDoc = $this->locations->findBolDocnum($docnum);
        if ($historyDoc !== null) {
            $this->locations->updateHistory($lastloc, $historyDoc, $vannum);
            $this->activities->record([
                'usrcde' => Text::clip($user->usrcde ?? '', 15),
                'usrname' => Text::clip($user->usrname ?? '', 30),
                'usrdte' => now()->format('Y-m-d H:i:s'),
                'usrtim' => now()->format('H:i:s'),
                'activity' => 'Change Van Location',
                'remarks' => "Change lastloc to $lastloc; vannum:$vannum; docnum:$historyDoc ",
                'webpage' => 'fix_vanloc.php',
                'module' => self::MODULE,
                'docnum' => $historyDoc,
            ], self::MODULE);
        }
    }
}
