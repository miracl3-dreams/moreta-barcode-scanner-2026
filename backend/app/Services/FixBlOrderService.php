<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\FixBlOrderRepository;
use App\Repositories\UserActivityRepository;
use App\Support\Text;
use Illuminate\Validation\ValidationException;

class FixBlOrderService
{
    private const MODULE = 'POS';

    public function __construct(
        private FixBlOrderRepository $orders,
        private UserActivityRepository $activities,
    ) {}

    /**
     * @return array{data: list<string>}
     */
    public function list(string $voynum): array
    {
        return ['data' => $this->orders->docnums($voynum)];
    }

    /**
     * @param  array{voynum: string, next_bl: string}  $validated
     */
    public function save(User $user, array $validated): void
    {
        $voynum = Text::clip($validated['voynum'] ?? '', 25);
        $nextBl = Text::clip($validated['next_bl'] ?? '', 5);
        $checkDocnum = str_replace('-', '', $voynum.$nextBl);

        if ($this->orders->docnumExists($checkDocnum)) {
            throw ValidationException::withMessages([
                'next_bl' => 'BL already exist! Kindly check BL No. inserted',
            ]);
        }

        $prev = (string) ($this->orders->voyageBolnum($voynum) ?? '');
        $this->orders->updateVoyageBolnum($voynum, $nextBl);
        $this->activities->record([
            'usrcde' => Text::clip($user->usrcde ?? '', 15),
            'usrname' => Text::clip($user->usrname ?? '', 30),
            'usrdte' => now()->format('Y-m-d H:i:s'),
            'usrtim' => now()->format('H:i:s'),
            'activity' => 'Fix BL No',
            'remarks' => 'Fix BL No [Voyage No. : '.$voynum.' ,Prev BL No.: '.$prev.' , Next BL No. : '.$nextBl.' ] ',
            'webpage' => 'trn_fix_bl.php',
            'module' => self::MODULE,
            'docnum' => $voynum,
        ], self::MODULE);
    }
}
