<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\FixBlNumberRepository;
use App\Repositories\UserActivityRepository;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FixBlNumberService
{
    private const MODULE = 'POS';

    public function __construct(
        private FixBlNumberRepository $numbers,
        private UserActivityRepository $activities,
    ) {}

    /**
     * @param  array{old_docnum: string, new_docnum: string}  $validated
     */
    public function replace(User $user, array $validated): void
    {
        $old = Text::clip($validated['old_docnum'] ?? '', 25);
        $new = Text::clip($validated['new_docnum'] ?? '', 25);

        if ($old === '' && $new === '') {
            throw ValidationException::withMessages(['old_docnum' => 'fill all info!']);
        }
        if ($old === '') {
            throw ValidationException::withMessages(['old_docnum' => 'fill old bl!']);
        }
        if ($new === '') {
            throw ValidationException::withMessages(['new_docnum' => 'fill new bl!']);
        }
        if (! $this->numbers->existsDocnum($old)) {
            throw ValidationException::withMessages(['old_docnum' => 'BL does not exist!']);
        }
        if ($old !== $new && $this->numbers->existsDocnum($new)) {
            throw ValidationException::withMessages([
                'new_docnum' => 'BL already exist! Kindly check BL No. inserted',
            ]);
        }
        if ($old === $new) {
            return;
        }

        $newDocapp = Text::clip('SAL-'.$new, 30);

        DB::transaction(function () use ($user, $old, $new, $newDocapp) {
            $this->numbers->rename($old, $new, $newDocapp);
            $this->activities->record([
                'usrcde' => Text::clip($user->usrcde ?? '', 15),
                'usrname' => Text::clip($user->usrname ?? '', 30),
                'usrdte' => now()->format('Y-m-d H:i:s'),
                'usrtim' => now()->format('H:i:s'),
                'activity' => 'Fix BL Number',
                'remarks' => "Change BL No. from $old to $new",
                'webpage' => 'fix_bl_docnum.php',
                'module' => self::MODULE,
                'docnum' => $new,
            ], self::MODULE);
        });
    }
}
