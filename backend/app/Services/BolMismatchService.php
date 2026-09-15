<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\BolMismatchRepository;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use Throwable;

class BolMismatchService
{
    /**
     * @var list<string>
     */
    private const CHARGE_FIELDS = [
        'untprc',
        'extprc',
        'editby',
        'frghtamt',
        'vatamt',
        'arrorgamt',
        'ppaorgamt',
        'arrdstamt',
        'trckorgamt',
        'trckdstamt',
        'weiorgamt',
        'storamt',
        'dummamt',
        'othrchrgamt',
        'ppadstamt',
        'subtot',
        'trntot',
        'trntotfor',
        'docbal',
        'docbalfor',
    ];

    private const MODULE = 'POS';

    public function __construct(private BolMismatchRepository $mismatch) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function searchMismatch(string $voynum): array
    {
        $this->mismatch->setGroupConcatMaxLen();
        $rows = [];
        foreach ($this->mismatch->cfHeaders($voynum) as $header) {
            $row = $this->mismatchRow((array) $header);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchMissing(string $voynum): array
    {
        $mains = $this->mismatch->mainHeaders($voynum);
        $cfVoyages = [];
        foreach ($mains as $main) {
            $cfVoyages[] = $this->restoreCf((string) ($main->main_voyage ?? ''));
        }
        $cfVoyages = array_values(array_unique($cfVoyages));
        $existingVoyages = $this->mismatch->existingVoyages($cfVoyages);
        $existingCf = $this->mismatch->existingCfKeys($cfVoyages);

        $rows = [];
        foreach ($mains as $main) {
            $mainVoyage = (string) ($main->main_voyage ?? '');
            $cfVoyage = $this->restoreCf($mainVoyage);
            if (! isset($existingVoyages[$cfVoyage])) {
                continue;
            }

            $expected = $this->expectedCfDocnum($cfVoyage, $mainVoyage, (string) ($main->main_docnum ?? ''));
            if (isset($existingCf[$cfVoyage.'|'.$expected])) {
                continue;
            }

            $shipper = trim((string) ($main->main_shipper ?? ''));
            $consignee = trim((string) ($main->main_consignee ?? ''));
            $rows[] = [
                'type' => 'missing',
                'status' => 'missing',
                'recid' => (int) ($main->main_recid ?? 0),
                'main_voyage' => $mainVoyage,
                'cf_voyage' => $cfVoyage,
                'main_docnum' => (string) ($main->main_docnum ?? ''),
                'expected_cf_docnum' => $expected,
                'cf_docnum' => $expected,
                'main_shipper' => $shipper,
                'cf_shipper' => '',
                'main_consignee' => $consignee,
                'cf_consignee' => '',
                'main_item_count' => 0,
                'cf_item_count' => 0,
                'issues' => ['Completely Missing CF Record'],
                'shipper_diff' => false,
                'consignee_diff' => false,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<int>  $recids
     * @return array{success: bool, message: string, fixed: int, errors: list<string>}
     */
    public function fixMismatch(array $recids, User $user): array
    {
        if ($recids === []) {
            return [
                'success' => false,
                'message' => 'No records selected to fix.',
                'fixed' => 0,
                'errors' => [],
            ];
        }

        $this->mismatch->setGroupConcatMaxLen();
        $fixed = 0;
        $errors = [];
        $processed = [];

        foreach ($recids as $recid) {
            $cfDocnum = '';
            try {
                $header = $this->mismatch->headerDocByRecid((int) $recid);
                if ($header === null) {
                    continue;
                }

                $cfDocnum = (string) ($header->docnum ?? '');
                $cfVoyage = (string) ($header->voynum ?? '');
                if (! in_array($cfVoyage, $processed, true)) {
                    $processed[] = $cfVoyage;
                }

                $profile = $this->mismatch->itemProfile($cfDocnum, $cfVoyage);
                $match = $this->findMainForCf($cfDocnum, $cfVoyage, $profile);
                if ($match['main'] === null) {
                    $errors[] = 'Matching Main Record not found for: '.$cfDocnum.' ('.$cfVoyage.')';

                    continue;
                }

                $mainDocnum = $match['main']['docnum'];
                $mainVoyage = $match['main_voyage'];
                $expected = $this->expectedCfDocnum($cfVoyage, $mainVoyage, $mainDocnum);
                if ($cfDocnum === $expected) {
                    continue;
                }

                DB::transaction(function () use ($cfDocnum, $cfVoyage, $expected, $recid, $user) {
                    $tempDocnum = 'TMP_'.$expected;
                    $tmpExists = $this->mismatch->headerExists($tempDocnum, $cfVoyage);
                    if (! $tmpExists) {
                        $this->mismatch->renameHeaderDocnum($expected, $tempDocnum, 'SAL-'.$tempDocnum, $cfVoyage);
                        $this->mismatch->renameLinesDocnum($expected, $tempDocnum, $cfVoyage);
                    }

                    $this->mismatch->updateHeaderDocnumByRecid($recid, $expected, 'SAL-'.$expected);
                    $this->mismatch->renameLinesDocnum($cfDocnum, $expected, $cfVoyage);
                    $this->logActivity(
                        $user,
                        'Mismatch Fix',
                        'Renamed shifted CF record from '.$cfDocnum.' to '.$expected.', preserving temp record for chained fixes.',
                        $expected,
                    );
                });
                $fixed++;
            } catch (Throwable $e) {
                $errors[] = 'Exception for '.$cfDocnum.': '.$e->getMessage();
            }
        }

        $this->mismatch->deleteTmpForVoyages($processed);

        $message = $fixed.' record(s) fixed.';
        if ($errors !== []) {
            $message .= ' ('.count($errors).' error(s))';
        }

        return [
            'success' => $fixed > 0,
            'message' => $message,
            'fixed' => $fixed,
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<int>  $recids
     * @return array{success: bool, message: string, fixed: int, errors: list<string>}
     */
    public function fixMissing(array $recids, User $user): array
    {
        if ($recids === []) {
            return [
                'success' => false,
                'message' => 'No records selected.',
                'fixed' => 0,
                'errors' => [],
            ];
        }

        $fixed = 0;
        $errors = [];

        foreach ($recids as $recid) {
            try {
                DB::transaction(function () use ($recid, $user, &$fixed, &$errors) {
                    $main = $this->mismatch->headerByRecid((int) $recid);
                    if ($main === null) {
                        return;
                    }

                    $mainRow = (array) $main;
                    $mainVoyage = (string) ($mainRow['voynum'] ?? '');
                    $cfVoyage = $this->restoreCf($mainVoyage);
                    $target = $this->expectedCfDocnum($cfVoyage, $mainVoyage, (string) ($mainRow['docnum'] ?? ''));

                    if ($this->mismatch->headerExists($target, $cfVoyage)) {
                        $errors[] = 'CF Record already exists: '.$target;

                        return;
                    }

                    $newHeader = $this->clearCharges($mainRow);
                    unset($newHeader['recid']);
                    $newHeader['docnum'] = $target;
                    $newHeader['voynum'] = $cfVoyage;
                    $newHeader['docapp'] = 'SAL-'.$target;
                    $this->mismatch->insertHeader($newHeader);

                    foreach ($this->mismatch->linesByDocnumVoyage((string) ($mainRow['docnum'] ?? ''), $mainVoyage) as $line) {
                        $detail = $this->clearCharges((array) $line);
                        unset($detail['recid']);
                        $detail['docnum'] = $target;
                        $detail['voynum'] = $cfVoyage;
                        $this->mismatch->insertLine($detail);
                    }

                    $this->logActivity(
                        $user,
                        'Missing CF Fix',
                        'Created missing CF record '.$target.' from Main '.(string) ($mainRow['docnum'] ?? ''),
                        $target,
                    );
                    $fixed++;
                });
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        return [
            'success' => $fixed > 0,
            'message' => $fixed.' record(s) created.'.($errors !== [] ? ' ('.count($errors).' errors)' : ''),
            'fixed' => $fixed,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $header
     * @return array<string, mixed>|null
     */
    private function mismatchRow(array $header): ?array
    {
        $cfDocnum = (string) ($header['docnum'] ?? '');
        $cfVoyage = (string) ($header['voynum'] ?? '');
        $cfProfile = $this->mismatch->itemProfile($cfDocnum, $cfVoyage);
        $match = $this->findMainForCf($cfDocnum, $cfVoyage, $cfProfile);
        if ($match['main'] === null) {
            return null;
        }

        $main = $match['main'];
        $mainVoyage = $match['main_voyage'];
        $mainProfile = $this->mismatch->itemProfile($main['docnum'], $mainVoyage);
        $expected = $this->expectedCfDocnum($cfVoyage, $mainVoyage, $main['docnum']);

        $cfShipper = trim((string) ($header['cuscde'] ?? ''));
        $cfConsignee = trim((string) ($header['concde'] ?? ''));
        if (trim((string) ($header['cuscde'] ?? 'CANCELLED')) === 'CANCELLED'
            && trim((string) ($header['concde'] ?? 'CANCELLED')) === 'CANCELLED') {
            return null;
        }

        $isCorrectDocnum = $cfDocnum === $expected;
        $isCorrectShipper = $cfShipper === trim($main['cuscde']);
        $isCorrectConsignee = $cfConsignee === trim($main['concde']);
        $isItemMismatch = ((int) $cfProfile['cnt'] !== (int) $mainProfile['cnt']
            || $cfProfile['item_list'] !== $mainProfile['item_list']);

        if ($isCorrectShipper && $isCorrectConsignee && $isItemMismatch) {
            return null;
        }
        if (! $isItemMismatch && $isCorrectDocnum) {
            return null;
        }

        $status = 'mismatch';
        $issues = [];
        if ($isItemMismatch) {
            $issues[] = 'Item List Mismatch';
        }
        if (! $isCorrectDocnum) {
            $issues[] = 'Shifted Docnum';
        }
        if (! $isCorrectShipper) {
            $issues[] = 'Shipper Mismatch';
        }
        if (! $isCorrectConsignee) {
            $issues[] = 'Consignee Mismatch';
        }
        if ($cfShipper === '') {
            $status = 'missing';
            $issues[] = 'Empty CF Shipper';
        }
        if ($cfConsignee === '') {
            $status = 'missing';
            $issues[] = 'Empty CF Consignee';
        }

        return [
            'type' => 'mismatch',
            'status' => $status,
            'recid' => (int) ($header['recid'] ?? 0),
            'main_voyage' => $mainVoyage,
            'cf_voyage' => $cfVoyage,
            'main_docnum' => $main['docnum'],
            'expected_cf_docnum' => $expected,
            'cf_docnum' => $cfDocnum,
            'main_shipper' => trim($main['cuscde']),
            'cf_shipper' => $cfShipper,
            'main_consignee' => trim($main['concde']),
            'cf_consignee' => $cfConsignee,
            'main_item_count' => (int) $mainProfile['cnt'],
            'cf_item_count' => (int) $cfProfile['cnt'],
            'issues' => $issues,
            'shipper_diff' => ! $isCorrectShipper,
            'consignee_diff' => ! $isCorrectConsignee,
        ];
    }

    /**
     * @param  array{cnt: int, item_list: string|null}  $cfProfile
     * @return array{main: array{docnum: string, cuscde: string, concde: string}|null, main_voyage: string, mode: string|null}
     */
    private function findMainForCf(string $cfDocnum, string $cfVoyage, array $cfProfile): array
    {
        $mainVoyage = str_replace('CF', '', $cfVoyage);
        $main = $this->mismatch->findMainByItemList($mainVoyage, (int) $cfProfile['cnt'], $cfProfile['item_list']);
        if ($main !== null) {
            return ['main' => $main, 'main_voyage' => $mainVoyage, 'mode' => 'item_list'];
        }

        $numericPart = str_replace($this->alnum($cfVoyage), '', $cfDocnum);
        $candidate = $this->alnum($mainVoyage).$numericPart;
        $main = $this->mismatch->findMainHeader($mainVoyage, $candidate);
        if ($main !== null) {
            return ['main' => $main, 'main_voyage' => $mainVoyage, 'mode' => 'docnum'];
        }

        return ['main' => null, 'main_voyage' => $mainVoyage, 'mode' => null];
    }

    private function expectedCfDocnum(string $cfVoyage, string $mainVoyage, string $mainDocnum): string
    {
        $numericPart = str_replace($this->alnum($mainVoyage), '', $mainDocnum);

        return $this->alnum($cfVoyage).$numericPart;
    }

    private function restoreCf(string $voyage): string
    {
        $parts = explode('-', $voyage);
        if (isset($parts[0]) && substr($parts[0], -2) !== 'CF') {
            $parts[0] .= 'CF';
        }

        return implode('-', $parts);
    }

    private function alnum(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]+/i', '', $value);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function clearCharges(array $row): array
    {
        foreach (self::CHARGE_FIELDS as $field) {
            if (array_key_exists($field, $row)) {
                $row[$field] = '';
            }
        }

        return $row;
    }

    private function logActivity(User $user, string $activity, string $remarks, string $docnum): void
    {
        $this->mismatch->insertActivity([
            'usrcde' => Text::clip($user->usrcde ?? '', 15),
            'usrname' => Text::clip($user->usrname ?? '', 30),
            'usrdte' => now()->format('Y-m-d H:i:s'),
            'usrtim' => now()->format('H:i:s'),
            'activity' => $activity,
            'remarks' => $remarks,
            'webpage' => 'tracing_fix_bol.php',
            'module' => self::MODULE,
            'docnum' => $docnum,
        ]);

        $max = $this->mismatch->userLogMaxRec();
        if ($max < 1) {
            return;
        }
        $count = $this->mismatch->activityCountForModule(self::MODULE);
        if ($count > $max) {
            $this->mismatch->pruneActivities(self::MODULE, $count - $max);
        }
    }
}
