<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\ShiftScheduleRepository;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftScheduleService
{
    /**
     * @var list<string>
     */
    private const SHIFTS = ['1s', '2s', '3s'];

    public function __construct(private ShiftScheduleRepository $shifts) {}

    /**
     * @return array{
     *     branch: string,
     *     is_supervisor: bool,
     *     current_shift: string,
     *     used_shifts: list<string>,
     *     branches: list<array{value: string, label: string}>
     * }
     */
    public function show(User $user): array
    {
        $syspar = $this->shifts->syspar();
        $current = trim((string) ($syspar->shiftcde ?? ''));
        if (! in_array($current, self::SHIFTS, true)) {
            $current = '3s';
        }

        return [
            'branch' => trim((string) ($user->brnchcde ?? '')),
            'is_supervisor' => $user->isAdmin(),
            'current_shift' => $current,
            'used_shifts' => $this->shifts->usedShiftCodesToday(now()->format('Y-m-d')),
            'branches' => $user->isAdmin() ? $this->shifts->branches() : [],
        ];
    }

    /**
     * @param  array{shiftcde: string, branchcde?: string}  $validated
     * @return array{
     *     branch: string,
     *     is_supervisor: bool,
     *     current_shift: string,
     *     used_shifts: list<string>,
     *     branches: list<array{value: string, label: string}>
     * }
     */
    public function change(User $user, array $validated): array
    {
        $shiftcde = Text::clip($validated['shiftcde'] ?? '', 20);
        if (! in_array($shiftcde, self::SHIFTS, true)) {
            throw ValidationException::withMessages([
                'shiftcde' => 'This field cannot be blank',
            ]);
        }

        $used = $this->shifts->usedShiftCodesToday(now()->format('Y-m-d'));
        if (in_array($shiftcde, $used, true)) {
            throw ValidationException::withMessages([
                'shiftcde' => 'This shift was already used today.',
            ]);
        }

        $branch = $user->isAdmin()
            ? Text::clip($validated['branchcde'] ?? '', 100)
            : Text::clip($user->brnchcde ?? '', 100);
        $usrcde = Text::clip($user->usrcde ?? '', 30);
        $now = now()->format('Y-m-d H:i:s');

        DB::transaction(function () use ($shiftcde, $now, $usrcde, $branch) {
            $this->shifts->updateSyspar($shiftcde, $now, $usrcde);
            $this->shifts->insertChange([
                'shiftcde' => $shiftcde,
                'shiftdte' => $now,
                'usrcde' => $usrcde,
                'branchcde' => $branch,
            ]);

            $row = [
                'shiftcde' => $shiftcde,
                'shiftdate' => $now,
                'usrcde' => Text::clip($usrcde, 100),
                'branchcde' => $branch,
            ];

            if ($this->shifts->branchShift($branch)) {
                $this->shifts->updateBranchShift($branch, $row);
            } else {
                $this->shifts->insertBranchShift($row);
            }
        });

        $result = $this->show($user);
        if ($user->isAdmin()) {
            $result['branch'] = $branch;
        }

        return $result;
    }
}
