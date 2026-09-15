<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\FixShiftRepository;
use App\Repositories\UserActivityRepository;
use App\Support\Text;
use Illuminate\Validation\ValidationException;

class FixShiftService
{
    private const MODULE = 'POS';

    public function __construct(
        private FixShiftRepository $shifts,
        private UserActivityRepository $activities,
    ) {}

    /**
     * @return array{branches: list<array{value: string, label: string}>}
     */
    public function lookups(): array
    {
        return ['branches' => $this->shifts->branches()];
    }

    /**
     * @param  array{docnum?: string, date_from?: string, date_to?: string, shiftcde?: string, trantype?: string}  $validated
     * @return list<array{trndte: string, docnum: string, shiftcde: string, brnchcde: string}>
     */
    public function search(array $validated): array
    {
        $table = $this->table((string) ($validated['trantype'] ?? 'exp'));
        $rows = $this->shifts->search($table, [
            'shiftcde' => trim((string) ($validated['shiftcde'] ?? '')),
            'docnum' => Text::clip($validated['docnum'] ?? '', 25),
            'date_from' => $this->sqlDate((string) ($validated['date_from'] ?? '')),
            'date_to' => $this->sqlDate((string) ($validated['date_to'] ?? '')),
        ]);

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'trndte' => trim((string) ($row->trndte ?? '')),
                'docnum' => trim((string) ($row->docnum ?? '')),
                'shiftcde' => trim((string) ($row->shiftcde ?? '')),
                'brnchcde' => trim((string) ($row->brnchcde ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param  array{trantype?: string, shiftcde_rep?: string, branch?: string, docnums?: list<string>}  $validated
     */
    public function save(User $user, array $validated): void
    {
        $replace = trim((string) ($validated['shiftcde_rep'] ?? ''));
        if ($replace === '') {
            return;
        }

        $table = $this->table((string) ($validated['trantype'] ?? 'exp'));
        $branch = Text::clip($validated['branch'] ?? '', 100);
        $docnums = $validated['docnums'] ?? [];

        foreach ($docnums as $raw) {
            $docnum = Text::clip($raw, 25);
            if ($docnum === '') {
                continue;
            }

            $current = $this->shifts->currentCodes($table, $docnum);
            $update = ['shiftcde' => Text::clip($replace, 20)];
            $remarkExtra = '';
            if ($branch !== '') {
                $update['brnchcde'] = $branch;
                $fromBranch = trim((string) ($current->brnchcde ?? ''));
                $remarkExtra = "; Change branchcde from $fromBranch to $branch ";
            }

            $this->activities->record([
                'usrcde' => Text::clip($user->usrcde ?? '', 15),
                'usrname' => Text::clip($user->usrname ?? '', 30),
                'usrdte' => now()->format('Y-m-d H:i:s'),
                'usrtim' => now()->format('H:i:s'),
                'activity' => 'Change Shift',
                'remarks' => 'Change shift from '.trim((string) ($current->shiftcde ?? '')).' to '.$replace.' '.$remarkExtra.'; with docnum-'.$docnum,
                'webpage' => 'fix_shift.php',
                'module' => self::MODULE,
                'docnum' => $replace,
            ], self::MODULE);

            $this->shifts->updateByDocnum($table, $docnum, $update);
        }
    }

    private function table(string $trantype): string
    {
        if ($trantype === 'or') {
            return 'arpaymentsfile1';
        }
        if ($trantype === 'exp') {
            return 'receivingfile2';
        }

        throw ValidationException::withMessages([
            'trantype' => 'This field cannot be blank',
        ]);
    }

    private function sqlDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
            return substr($value, 0, 10);
        }

        $parsed = \DateTime::createFromFormat('m-d-Y', $value);
        if ($parsed instanceof \DateTime) {
            return $parsed->format('Y-m-d');
        }

        return $value;
    }
}
