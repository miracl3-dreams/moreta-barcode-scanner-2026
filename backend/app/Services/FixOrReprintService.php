<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\FixOrReprintRepository;
use App\Support\Text;
use Illuminate\Validation\ValidationException;

class FixOrReprintService
{
    private const MODULE = 'POS';

    public function __construct(private FixOrReprintRepository $receipts) {}

    /**
     * @param  array{docnum: string}  $validated
     */
    public function allow(User $user, array $validated): void
    {
        $docnum = Text::clip($validated['docnum'] ?? '', 25);
        $recid = $this->receipts->findRecidByDocnum($docnum);
        if ($recid === null) {
            throw ValidationException::withMessages([
                'docnum' => 'OR does not exist!',
            ]);
        }

        $this->receipts->allowReprint($recid);
        $this->logActivity(
            $user,
            'OR PRINTING',
            'Allowed OR re-printing; docnum:'.$docnum,
            $docnum,
        );
    }

    private function logActivity(User $user, string $activity, string $remarks, string $docnum): void
    {
        $this->receipts->insertActivity([
            'usrcde' => Text::clip($user->usrcde ?? '', 15),
            'usrname' => Text::clip($user->usrname ?? '', 30),
            'usrdte' => now()->format('Y-m-d H:i:s'),
            'usrtim' => now()->format('H:i:s'),
            'activity' => $activity,
            'remarks' => $remarks,
            'webpage' => 'fix_or_print.php',
            'module' => self::MODULE,
            'docnum' => $docnum,
        ]);

        $max = $this->receipts->userLogMaxRec();
        if ($max < 1) {
            return;
        }
        $count = $this->receipts->activityCountForModule(self::MODULE);
        if ($count > $max) {
            $this->receipts->pruneActivities(self::MODULE, $count - $max);
        }
    }
}
