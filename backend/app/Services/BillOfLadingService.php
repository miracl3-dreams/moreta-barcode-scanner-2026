<?php

namespace App\Services;

use App\Models\BillOfLading;
use App\Models\BillOfLadingLine;
use App\Models\User;
use App\Models\Voyage;
use App\Repositories\BillOfLadingRepository;
use App\Repositories\ConsigneeRepository;
use App\Repositories\ShipperRepository;
use App\Repositories\VoyageRepository;
use App\Support\Amount;
use App\Support\MenuPermission;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillOfLadingService
{
    private const MENPRG = 'view_bol.php';

    public function __construct(
        private BillOfLadingRepository $bills,
        private VoyageRepository $voyages,
        private ShipperRepository $shippers,
        private ConsigneeRepository $consignees,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function lookups(Voyage $voyage, User $user): array
    {
        return [
            'detail_max' => $this->bills->detailMax(),
            'shippers' => $this->bills->shipperOptions(),
            'consignees' => $this->bills->consigneeOptions(),
            'payment_terms' => $this->bills->paymentTermOptions(),
            'shipment_modes' => $this->bills->shipmentModeOptions(),
            'credit_terms' => $this->bills->creditTermOptions(),
            'checkers' => $this->bills->checkerOptions(),
            'units' => $this->bills->unitOptions(),
            'categories' => $this->bills->bolCategoryOptions(),
            'empty_van_categories' => $this->bills->emptyVanCategoryOptions(),
            'charges' => $this->bills->chargeFields($user),
            'locked' => $this->isLocked($voyage, $user),
            'typby' => trim((string) ($user->usrname ?? '')),
            'can_rates' => $this->canRates($user),
        ];
    }

    /**
     * @return list<string>
     */
    public function freeVans(Voyage $voyage, string $catcde): array
    {
        return $this->bills->freeVans(
            trim((string) ($voyage->origin ?? '')),
            trim((string) ($voyage->voytype ?? '')),
            $catcde,
        );
    }

    /**
     * @return array{header: array<string, mixed>, lines: list<array<string, mixed>>, locked: bool}
     */
    public function show(Voyage $voyage, int $recid, User $user): array
    {
        $bill = $this->bills->findDetail($recid);
        if ($bill === null || trim((string) $bill->voynum) !== trim((string) $voyage->voynum)) {
            throw ValidationException::withMessages([
                'recid' => ['Bill of lading was not found.'],
            ]);
        }

        $voynum = trim((string) $bill->voynum);
        $docnum = trim((string) $bill->docnum);
        $docapp = trim((string) ($bill->docapp ?? ''));
        if ($docapp === '') {
            $docapp = 'SAL-'.$docnum;
        }
        $payments = $this->bills->paymentsForDocapp($docapp);
        $paymentAmount = 0.0;
        foreach ($payments as $payment) {
            $paymentAmount += Amount::parse($payment['amtapp']);
        }

        return [
            'header' => array_merge($bill->toDetailArray(), [
                'docapp' => $docapp,
                'docbalfor' => Amount::format($bill->docbalfor),
                'payment_amount' => Amount::format($paymentAmount),
            ]),
            'payments' => $payments,
            'lines' => $this->bills->listCargoLines($voynum, $docnum)
                ->map(function ($row) {
                    $payload = $row->toCargoArray();
                    if ($payload['catcde'] === '' && $payload['vannum'] !== '') {
                        $payload['catcde'] = $this->bills->categoryForVan($payload['vannum']);
                    }

                    return $payload;
                })
                ->values()
                ->all(),
            'locked' => $this->isLocked($voyage, $user),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{header: array<string, mixed>, lines: list<array<string, mixed>>, locked: bool}
     */
    public function create(Voyage $voyage, array $validated, User $user): array
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_ADD);
        $this->assertNotLocked($voyage, $user);
        $this->assertCanSave($validated);

        $bill = DB::transaction(function () use ($voyage, $validated, $user) {
            $voynum = trim((string) ($voyage->voynum ?? ''));
            $docnum = $this->voyages->allocateDocnum($voynum);
            if ($docnum === '') {
                throw ValidationException::withMessages([
                    'docnum' => ['Something went wrong in saving data. Please try to click save button again'],
                ]);
            }
            if ($this->bills->existsDocnum($docnum)) {
                throw ValidationException::withMessages([
                    'docnum' => ['BL Number already exist.'],
                ]);
            }

            return $this->persist($voyage, $validated, $user, $docnum, null);
        });

        return $this->show($voyage, (int) $bill->recid, $user);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{header: array<string, mixed>, lines: list<array<string, mixed>>, locked: bool}
     */
    public function update(Voyage $voyage, int $recid, array $validated, User $user): array
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_EDIT);
        $this->assertNotLocked($voyage, $user);
        $this->assertCanSave($validated);

        $bill = DB::transaction(function () use ($voyage, $recid, $validated, $user) {
            $existing = $this->bills->findDetailOnVoyage(trim((string) $voyage->voynum), $recid);
            if ($existing === null) {
                throw ValidationException::withMessages([
                    'recid' => ['This billing has already been modified or deleted by another user. Please close this form and reopen it to get the latest data.'],
                ]);
            }

            return $this->persist($voyage, $validated, $user, trim((string) $existing->docnum), $existing);
        });

        return $this->show($voyage, (int) $bill->recid, $user);
    }

    public function delete(Voyage $voyage, int $recid, User $user): void
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_DELETE);
        $this->assertNotLocked($voyage, $user);

        DB::transaction(function () use ($voyage, $recid) {
            $bill = $this->bills->findDetailOnVoyage(trim((string) $voyage->voynum), $recid);
            if ($bill === null) {
                return;
            }

            $voynum = trim((string) $bill->voynum);
            $docnum = trim((string) $bill->docnum);

            $this->bills->unlockSealsForDocument($voynum, $docnum);
            $this->bills->clearBillingTag($bill->billing_no !== null ? (string) $bill->billing_no : null);
            $this->bills->deleteHeader((int) $bill->recid);
            $this->bills->deleteLines($voynum, $docnum);

            $this->deleteTwinDocuments($voynum, $docnum);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function emptyVans(Voyage $voyage): array
    {
        return $this->bills->listEmptyVans(trim((string) $voyage->voynum))
            ->map(fn ($row) => $row->toEmptyVanArray())
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function emptyVanContainers(Voyage $voyage, string $catcde): array
    {
        return $this->bills->emptyVanContainers(trim((string) ($voyage->origin ?? '')), $catcde);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function createEmptyVan(Voyage $voyage, array $validated, User $user): array
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_ADD);

        $line = DB::transaction(function () use ($voyage, $validated) {
            return $this->persistEmptyVan($voyage, $validated, null);
        });

        return $line->toEmptyVanArray();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function updateEmptyVan(Voyage $voyage, int $recid, array $validated, User $user): array
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_EDIT);

        $line = DB::transaction(function () use ($voyage, $recid, $validated) {
            $existing = $this->bills->findEmptyVan(trim((string) $voyage->voynum), $recid);
            if ($existing === null) {
                throw ValidationException::withMessages([
                    'recid' => ['Empty van was not found.'],
                ]);
            }

            return $this->persistEmptyVan($voyage, $validated, $existing);
        });

        return $line->toEmptyVanArray();
    }

    public function deleteEmptyVan(Voyage $voyage, int $recid, User $user): void
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_DELETE);

        $existing = $this->bills->findEmptyVan(trim((string) $voyage->voynum), $recid);
        if ($existing === null) {
            return;
        }

        $this->bills->deleteEmptyVan($recid);
    }

    /**
     * @return list<string>
     */
    public function searchSeals(string $search): array
    {
        return $this->bills->searchSeals(Text::clip($search, 30));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function addSeal(array $validated): void
    {
        $this->supervisorFromOverride($validated['usrcde'] ?? '', $validated['usrpwd'] ?? '', 'Only Supervisor are allowed to override.');
        $sealnum = Text::clip($validated['sealnum'] ?? '', 30);
        if ($sealnum === '') {
            throw ValidationException::withMessages([
                'sealnum' => ['Please fill out required fields'],
            ]);
        }
        if ($this->bills->sealExists($sealnum)) {
            throw ValidationException::withMessages([
                'sealnum' => ['Duplicate Entry.'],
            ]);
        }

        $this->bills->createSeal($sealnum);
    }

    /**
     * @return list<array{recid: int, catcde: string, remarks: string, rate: string}>
     */
    public function rates(array $validated): array
    {
        $payee = strtolower(trim((string) ($validated['payee'] ?? 'shipper')));
        $cuscde = Text::clip($validated['cuscde'] ?? '', 100);
        $cusdsc = Text::clip($validated['cusdsc'] ?? '', 100);
        $concde = Text::clip($validated['concde'] ?? '', 50);
        $condsc = Text::clip($validated['condsc'] ?? '', 100);

        if ($payee === 'consignee') {
            if ($concde === '' || $condsc === '' || ! $this->bills->consigneeMatches($concde, $condsc)) {
                throw ValidationException::withMessages([
                    'concde' => ['Invalid Shipper/Consignee'],
                ]);
            }

            return $this->bills->ratesForPayee('consignee', $concde);
        }

        if ($cuscde === '' || $cusdsc === '' || ! $this->bills->shipperMatches($cuscde, $cusdsc)) {
            throw ValidationException::withMessages([
                'cuscde' => ['Invalid Shipper/Consignee'],
            ]);
        }

        return $this->bills->ratesForPayee('shipper', $cuscde);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{catcde: string, remarks: string, rate: string}
     */
    public function addRate(array $validated, User $user): array
    {
        $this->supervisorFromOverride($validated['usrcde'] ?? '', $validated['usrpwd'] ?? '', 'Only Supervisor are allowed to override.');
        $cuscde = Text::clip($validated['cuscde'] ?? '', 30);
        $catcde = Text::clip($validated['catcde'] ?? '', 30);
        $remarks = Text::clip($validated['remarks'] ?? '', 50);
        $rate = Amount::parse($validated['rate'] ?? 0);
        if ($cuscde === '') {
            throw ValidationException::withMessages([
                'cuscde' => ['Invalid Shipper.'],
            ]);
        }
        if ($catcde === '') {
            throw ValidationException::withMessages([
                'catcde' => ['Invalid User Category.'],
            ]);
        }
        if ($rate <= 0) {
            throw ValidationException::withMessages([
                'rate' => ['Invalid User rate.'],
            ]);
        }

        $this->bills->createCategoryRate([
            'usrcde' => Text::clip($user->usrcde ?? '', 20),
            'cuscde' => $cuscde,
            'catcde' => $catcde,
            'remarks' => $remarks,
            'rate' => $rate,
        ]);

        return [
            'catcde' => $catcde,
            'remarks' => $remarks,
            'rate' => Amount::display($rate),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{cuscde: string, cusdsc: string, telno: string}
     */
    public function addShipper(array $validated): array
    {
        $this->supervisorFromOverride($validated['usrcde'] ?? '', $validated['usrpwd'] ?? '', 'Only Supervisor are allowed.');
        $cusdsc = Text::clip($validated['cusdsc'] ?? '', 100);
        $cuscde = Text::clip(str_replace(' ', '', $cusdsc), 25);
        $telno = Text::clip($validated['telno'] ?? '', 30);
        $cusadd1 = Text::clip($validated['cusadd1'] ?? '', 100);
        $tinnum = Text::clip($validated['tinnum'] ?? '', 30);
        if ($cusdsc === '' || $telno === '' || $cusadd1 === '' || $tinnum === '') {
            throw ValidationException::withMessages([
                'cusdsc' => ['Some fields are required'],
            ]);
        }
        if ($this->shippers->existsByCodeOrName($cuscde, $cusdsc)) {
            throw ValidationException::withMessages([
                'cusdsc' => ['Shipper already exist.'],
            ]);
        }

        $shipper = $this->shippers->create([
            'cuscde' => $cuscde,
            'cusdsc' => $cusdsc,
            'telno' => $telno,
            'cusadd1' => $cusadd1,
            'tinnum' => $tinnum,
        ]);

        return [
            'cuscde' => trim((string) $shipper->cuscde),
            'cusdsc' => trim((string) $shipper->cusdsc),
            'telno' => trim((string) ($shipper->telno ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{concde: string, condsc: string, telnum: string}
     */
    public function addConsignee(array $validated): array
    {
        $this->supervisorFromOverride($validated['usrcde'] ?? '', $validated['usrpwd'] ?? '', 'Only Supervisor are allowed.');
        $condsc = Text::clip($validated['condsc'] ?? '', 100);
        $concde = Text::clip(str_replace(' ', '', $condsc), 25);
        $telnum = Text::clip($validated['telnum'] ?? '', 100);
        $conadd1 = Text::clip($validated['conadd1'] ?? '', 100);
        $tinnum = Text::clip($validated['tinnum'] ?? '', 30);
        if ($condsc === '' || $telnum === '' || $conadd1 === '' || $tinnum === '') {
            throw ValidationException::withMessages([
                'condsc' => ['Some fields are required'],
            ]);
        }
        if ($this->consignees->existsByCodeOrName($concde, $condsc)) {
            throw ValidationException::withMessages([
                'condsc' => ['Consignee already exist.'],
            ]);
        }

        $consignee = $this->consignees->create([
            'concde' => $concde,
            'condsc' => $condsc,
            'telnum' => $telnum,
            'conadd1' => $conadd1,
            'tinnum' => $tinnum,
        ]);

        return [
            'concde' => trim((string) $consignee->concde),
            'condsc' => trim((string) $consignee->condsc),
            'telnum' => trim((string) ($consignee->telnum ?? '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function printBill(Voyage $voyage, int $recid, User $user): array
    {
        $this->assertCanPrint($user);
        $bill = $this->bills->findDetail($recid);
        if ($bill === null || trim((string) $bill->voynum) !== trim((string) $voyage->voynum)) {
            throw ValidationException::withMessages([
                'recid' => ['Bill of lading was not found.'],
            ]);
        }

        return $this->printPayload($voyage, $bill);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function printVoyage(Voyage $voyage, User $user): array
    {
        $this->assertCanPrint($user);
        $pages = [];
        foreach ($this->bills->listByVoynum(trim((string) $voyage->voynum)) as $row) {
            if (trim((string) $row->docnum) === '') {
                continue;
            }
            $detail = $this->bills->findDetail((int) $row->recid);
            if ($detail === null) {
                continue;
            }
            $pages[] = $this->printPayload($voyage, $detail);
        }

        return $pages;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function persist(
        Voyage $voyage,
        array $validated,
        User $user,
        string $docnum,
        ?BillOfLading $existing,
    ): BillOfLading {
        $voynum = trim((string) ($voyage->voynum ?? ''));
        $usrname = Text::clip($user->usrname ?? '', 20);
        $addby = $existing !== null
            ? Text::clip($existing->addby ?? '', 15)
            : Text::clip($usrname, 15);
        $typby = $existing !== null
            ? Text::clip($existing->typby ?: $existing->addby, 30)
            : Text::clip($usrname, 30);
        $boldte = $existing !== null && $this->dateOrNull($existing->boldte) !== null
            ? $this->dateOrNull($existing->boldte)
            : date('Y-m-d');
        $trndte = $this->dateOrNull($voyage->trndte) ?? date('Y-m-d');
        $etadte = $this->dateOrNull($voyage->etadte);
        $origin = Text::clip($voyage->origin ?? '', 50);
        $dstcde = Text::clip($voyage->dstcde ?? '', 50);
        $vsslcde = Text::clip($voyage->vsslcde ?? '', 30);
        $voytyp = Text::clip($voyage->voytype ?? '', 30);
        $chkby = Text::clip($validated['chkby'] ?? '', 30);
        $cretrmcde = Text::clip($validated['cretrmcde'] ?? '', 10);

        $charges = [];
        $trntot = 0.0;
        foreach (BillOfLading::CHARGE_FIELDS as $field) {
            $amount = Amount::parse($validated[$field] ?? 0);
            $charges[$field] = $amount;
            if ($field !== 'vatamt') {
                $trntot += $amount;
            }
        }

        $lines = $this->cargoLines($validated['lines'] ?? []);
        $totpac = 0.0;
        $subtot = 0.0;
        foreach ($lines as $line) {
            $totpac += Amount::parse($line['qty']);
            $subtot += Amount::parse($line['extprc']);
        }

        $duedate = $trndte;
        $days = $cretrmcde !== '' ? $this->bills->termDays($cretrmcde) : null;
        if ($days !== null) {
            $duedate = date('Y-m-d', strtotime($trndte.' + '.$days.' days')) ?: $trndte;
        }

        $previousSeals = $existing === null
            ? []
            : $this->bills->documentSeals($voynum, $docnum);

        if ($existing !== null) {
            $this->bills->deleteHeader((int) $existing->recid);
            $this->bills->deleteLines($voynum, $docnum);
        }

        $header = array_merge($charges, [
            'voynum' => Text::clip($voynum, 30),
            'voytyp' => $voytyp,
            'docnum' => Text::clip($docnum, 25),
            'trndte' => $trndte,
            'etadte' => $etadte,
            'usrnam' => $typby,
            'addby' => $addby,
            'editby' => $existing !== null ? Text::clip($usrname, 15) : '',
            'docbal' => $trntot,
            'docbalfor' => $trntot,
            'trntotfor' => $trntot,
            'docapp' => Text::clip('SAL-'.$docnum, 30),
            'currte' => 1,
            'origin' => $origin,
            'dstcde' => $dstcde,
            'vsslcde' => $vsslcde,
            'trncde' => 'BL',
            'paytyp' => Text::clip($validated['paytrmcde'] ?? '', 30),
            'origamt' => (string) $trntot,
            'boldte' => $boldte,
            'totpac' => $totpac,
            'subtot' => $subtot,
            'trntot' => $trntot,
            'duedate' => $duedate,
            'dsttelno' => Text::clip($validated['dsttelno'] ?? '', 50),
            'payee' => Text::clip($validated['payee'] ?? '', 25),
            'paytrmcde' => Text::clip($validated['paytrmcde'] ?? '', 30),
            'shipmode' => Text::clip($validated['shipmode'] ?? '', 25),
            'remarks' => Text::clip($validated['remarks'] ?? '', 150),
            'cuscde' => Text::clip($validated['cuscde'] ?? '', 100),
            'cusdsc' => Text::clip($validated['cusdsc'] ?? '', 100),
            'concde' => Text::clip($validated['concde'] ?? '', 50),
            'condsc' => Text::clip($validated['condsc'] ?? '', 100),
            'chkby' => $chkby,
            'typby' => $typby,
            'cretrmcde' => $cretrmcde,
            'cretrmdesc' => Text::clip($validated['cretrmdesc'] ?? '', 20),
        ]);

        $created = $this->bills->createHeader($header);
        $now = date('Y-m-d H:i:s');
        $vans = [];

        foreach ($lines as $index => $line) {
            $soc = ! empty($line['soc']);
            $this->bills->createLine([
                'voynum' => Text::clip($voynum, 30),
                'voytyp' => $voytyp,
                'docnum' => Text::clip($docnum, 25),
                'usrnam' => $typby,
                'trndte' => $trndte,
                'vsslcde' => $vsslcde,
                'origin' => Text::clip($origin, 30),
                'dstcde' => $dstcde,
                'trncde' => 'BL',
                'payee' => Text::clip($validated['payee'] ?? '', 30),
                'concde' => Text::clip($validated['concde'] ?? '', 50),
                'cuscde' => Text::clip($validated['cuscde'] ?? '', 100),
                'chkr' => $chkby,
                'linenum' => (int) ($line['linenum'] ?? ($index + 1)),
                'catcde' => Text::clip($line['catcde'] ?? '', 100),
                'itmcde' => Text::clip($line['itmcde'] ?? '', 100),
                'qty' => Amount::parse($line['qty'] ?? 0),
                'class' => Text::clip($line['class'] ?? '', 30),
                'classdsc' => Text::clip($line['classdsc'] ?? '', 150),
                'profnum' => Text::clip($line['profnum'] ?? '', 150),
                'soc' => $soc ? 'Y' : 'N',
                'socvannum' => $soc ? Text::clip($line['socvannum'] ?? '', 20) : '',
                'vannum' => $soc ? '' : Text::clip($line['vannum'] ?? '', 30),
                'sealnum' => Text::clip($line['sealnum'] ?? '', 30),
                'model' => Text::clip($line['model'] ?? '', 20),
                'plate' => Text::clip($line['plate'] ?? '', 20),
                'constckr' => Text::clip($line['constckr'] ?? '', 20),
                'value' => Amount::parse($line['value'] ?? 0),
                'weiamt' => Amount::parse($line['weiamt'] ?? 0),
                'itmqty' => Amount::parse($line['itmqty'] ?? 0),
                'untmea' => Text::clip($line['untmea'] ?? '', 5),
                'untprc' => Amount::parse($line['untprc'] ?? 0),
                'extprc' => Amount::parse($line['extprc'] ?? 0),
                'dettyp' => trim((string) ($line['catcde'] ?? '')) === '' ? 'C' : 'I',
                'adddte' => $now,
            ]);

            if (! $soc) {
                $van = Text::clip($line['vannum'] ?? '', 30);
                if ($van !== '') {
                    $vans[] = $van;
                }
            }
        }

        $isFree = $this->vanIsFree($voyage);
        foreach (array_unique($vans) as $van) {
            $this->bills->updateVanLocation($van, $dstcde, $isFree);
        }

        $this->syncSeals($previousSeals, $lines);
        $this->persistTwinCopy($voyage, $header, $lines, $existing === null);

        return $created;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function persistEmptyVan(Voyage $voyage, array $validated, ?BillOfLadingLine $existing): BillOfLadingLine
    {
        $soc = (bool) ($validated['soc'] ?? false);
        $vannum = $soc ? '' : Text::clip($validated['vannum'] ?? '', 30);
        $socvannum = $soc ? Text::clip($validated['socvannum'] ?? '', 20) : '';
        if ($soc && $socvannum === '') {
            throw ValidationException::withMessages([
                'socvannum' => ['Please fill out required fields'],
            ]);
        }
        if (! $soc && $vannum === '') {
            throw ValidationException::withMessages([
                'vannum' => ['Please fill out required fields'],
            ]);
        }

        if ($existing !== null) {
            $this->bills->deleteEmptyVan((int) $existing->recid);
        }

        $line = $this->bills->createLine([
            'voynum' => Text::clip($voyage->voynum ?? '', 30),
            'voytyp' => Text::clip($voyage->voytype ?? '', 30),
            'trncde' => 'EV',
            'trndte' => $this->dateOrNull($voyage->trndte),
            'catcde' => Text::clip($validated['catcde'] ?? '', 100),
            'soc' => $soc ? 'Y' : 'N',
            'vannum' => $vannum,
            'socvannum' => $socvannum,
        ]);

        if ($vannum !== '') {
            $this->bills->updateVanLocation(
                $vannum,
                Text::clip($voyage->dstcde ?? '', 50),
            );
        }

        return $line;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertCanSave(array $validated): void
    {
        $cuscde = trim((string) ($validated['cuscde'] ?? ''));
        $concde = trim((string) ($validated['concde'] ?? ''));
        if (! $this->bills->shipperExists($cuscde)) {
            throw ValidationException::withMessages([
                'cuscde' => ['Customer was not valid.'],
            ]);
        }
        if (! $this->bills->consigneeExists($concde)) {
            throw ValidationException::withMessages([
                'concde' => ['Consignee was not valid.'],
            ]);
        }

        $cretrmcde = trim((string) ($validated['cretrmcde'] ?? ''));
        $cretrmdesc = trim((string) ($validated['cretrmdesc'] ?? ''));
        if ($cretrmdesc !== '' && ! $this->bills->creditTermExists($cretrmcde, $cretrmdesc)) {
            throw ValidationException::withMessages([
                'cretrmdesc' => ['Invalid Credit Term.'],
            ]);
        }
    }

    private function assertNotLocked(Voyage $voyage, User $user): void
    {
        if ($this->isLocked($voyage, $user)) {
            throw ValidationException::withMessages([
                'docnum' => ['This billing has already been modified or deleted by another user. Please close this form and reopen it to get the latest data.'],
            ]);
        }
    }

    private function isLocked(Voyage $voyage, User $user): bool
    {
        if (strtoupper(trim((string) ($user->usrlvl ?? ''))) !== 'USER') {
            return false;
        }

        return $this->bills->voyageIsApproved(trim((string) ($voyage->voynum ?? '')));
    }

    private function canRates(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $perms = $this->voyages->menuPermissions((string) $user->usrcde);

        return $perms !== null && $perms['allow_rates'];
    }

    private function assertCanPrint(User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $perms = $this->voyages->menuPermissions((string) $user->usrcde);
        if ($perms === null || ! $perms['allow_print']) {
            throw ValidationException::withMessages([
                'docnum' => ['You are not allowed to print.'],
            ]);
        }
    }

    private function supervisorFromOverride(mixed $usrcde, mixed $usrpwd, string $denied): User
    {
        $code = trim((string) $usrcde);
        $password = (string) $usrpwd;
        $user = User::query()->where('usrcde', $code)->first();
        if ($user === null || ! $user->passwordMatches($password)) {
            throw ValidationException::withMessages([
                'usrcde' => ['Invalid Username or Password'],
            ]);
        }
        if (strtoupper(trim((string) ($user->usrlvl ?? ''))) !== 'SUPERVISOR') {
            throw ValidationException::withMessages([
                'usrcde' => [$denied],
            ]);
        }

        return $user;
    }

    /**
     * @param  list<string>  $previous
     * @param  list<array<string, mixed>>  $lines
     */
    private function syncSeals(array $previous, array $lines): void
    {
        $next = [];
        foreach ($lines as $line) {
            $seal = Text::clip($line['sealnum'] ?? '', 30);
            if ($seal !== '') {
                $next[] = $seal;
                $this->bills->lockSeal($seal);
            }
        }
        foreach (array_diff($previous, $next) as $seal) {
            $this->bills->unlockSeal($seal);
        }
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $lines
     */
    private function persistTwinCopy(Voyage $voyage, array $header, array $lines, bool $isAdd): void
    {
        $voynum = trim((string) ($voyage->voynum ?? ''));
        $twinVoynum = $this->duplicateVoyageId($voynum);
        if ($twinVoynum === $voynum || ! $this->bills->voyageExists($twinVoynum)) {
            return;
        }

        $sourceDoc = trim((string) ($header['docnum'] ?? ''));
        $preserved = [];
        $preservedRates = [];
        if ($isAdd) {
            $twinDoc = $this->voyages->allocateDocnum($twinVoynum);
        } else {
            $twinDoc = $this->twinDocnum($twinVoynum, $sourceDoc);
            $existingTwin = $this->bills->findDetailByVoynumDocnum($twinVoynum, $twinDoc);
            if ($existingTwin !== null) {
                foreach (BillOfLading::CHARGE_FIELDS as $field) {
                    $preserved[$field] = Amount::parse($existingTwin->{$field});
                }
                $preserved['subtot'] = Amount::parse($existingTwin->subtot);
                $preserved['trntot'] = Amount::parse($existingTwin->trntot);
                $preserved['trntotfor'] = Amount::parse($existingTwin->trntot);
                $preserved['docbal'] = Amount::parse($existingTwin->trntot);
                $preserved['docbalfor'] = Amount::parse($existingTwin->trntot);
                $preserved['editby'] = trim((string) ($existingTwin->editby ?? ''));
                foreach ($this->bills->listCargoLines($twinVoynum, $twinDoc) as $line) {
                    $preservedRates[(int) $line->linenum] = [
                        'untprc' => Amount::parse($line->untprc),
                        'extprc' => Amount::parse($line->extprc),
                    ];
                }
                $this->bills->deleteHeader((int) $existingTwin->recid);
                $this->bills->deleteLines($twinVoynum, $twinDoc);
            }
        }

        if ($twinDoc === '') {
            return;
        }

        $twinHeader = $header;
        $twinHeader['voynum'] = Text::clip($twinVoynum, 30);
        $twinHeader['docnum'] = Text::clip($twinDoc, 25);
        $twinHeader['docapp'] = Text::clip('SAL-'.$twinDoc, 30);
        $twinHeader['voytyp'] = 'Cons';
        if ($isAdd) {
            foreach (BillOfLading::CHARGE_FIELDS as $field) {
                $twinHeader[$field] = 0;
            }
            $twinHeader['subtot'] = 0;
            $twinHeader['trntot'] = 0;
            $twinHeader['trntotfor'] = 0;
            $twinHeader['docbal'] = 0;
            $twinHeader['docbalfor'] = 0;
            $twinHeader['origamt'] = '0';
        } elseif (! empty($preserved)) {
            $twinHeader = array_merge($twinHeader, $preserved);
        }

        $this->bills->createHeader($twinHeader);

        foreach ($lines as $index => $line) {
            $soc = ! empty($line['soc']);
            $linenum = (int) ($line['linenum'] ?? ($index + 1));
            $untprc = $isAdd ? 0.0 : Amount::parse($preservedRates[$linenum]['untprc'] ?? ($line['untprc'] ?? 0));
            $extprc = $isAdd ? 0.0 : Amount::parse($preservedRates[$linenum]['extprc'] ?? ($line['extprc'] ?? 0));
            $this->bills->createLine([
                'voynum' => Text::clip($twinVoynum, 30),
                'voytyp' => 'Cons',
                'docnum' => Text::clip($twinDoc, 25),
                'usrnam' => $header['usrnam'] ?? '',
                'trndte' => $header['trndte'] ?? null,
                'vsslcde' => $header['vsslcde'] ?? '',
                'origin' => Text::clip($header['origin'] ?? '', 30),
                'dstcde' => $header['dstcde'] ?? '',
                'trncde' => 'BL',
                'payee' => $header['payee'] ?? '',
                'concde' => $header['concde'] ?? '',
                'cuscde' => $header['cuscde'] ?? '',
                'chkr' => $header['chkby'] ?? '',
                'linenum' => $linenum,
                'catcde' => Text::clip($line['catcde'] ?? '', 100),
                'itmcde' => Text::clip($line['itmcde'] ?? '', 100),
                'qty' => Amount::parse($line['qty'] ?? 0),
                'class' => Text::clip($line['class'] ?? '', 30),
                'classdsc' => Text::clip($line['classdsc'] ?? '', 150),
                'profnum' => Text::clip($line['profnum'] ?? '', 150),
                'soc' => $soc ? 'Y' : 'N',
                'socvannum' => $soc ? Text::clip($line['socvannum'] ?? '', 20) : '',
                'vannum' => $soc ? '' : Text::clip($line['vannum'] ?? '', 30),
                'sealnum' => Text::clip($line['sealnum'] ?? '', 30),
                'model' => Text::clip($line['model'] ?? '', 20),
                'plate' => Text::clip($line['plate'] ?? '', 20),
                'constckr' => Text::clip($line['constckr'] ?? '', 20),
                'value' => Amount::parse($line['value'] ?? 0),
                'weiamt' => Amount::parse($line['weiamt'] ?? 0),
                'itmqty' => Amount::parse($line['itmqty'] ?? 0),
                'untmea' => Text::clip($line['untmea'] ?? '', 5),
                'untprc' => $untprc,
                'extprc' => $extprc,
                'dettyp' => trim((string) ($line['catcde'] ?? '')) === '' ? 'C' : 'I',
                'adddte' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function twinDocnum(string $twinVoynum, string $docnum): string
    {
        $prefix = preg_replace('/[^a-z0-9]/i', '', $twinVoynum) ?? '';

        return $prefix.substr($docnum, -3);
    }

    /**
     * @return array<string, mixed>
     */
    private function printPayload(Voyage $voyage, BillOfLading $bill): array
    {
        $header = $bill->toDetailArray();
        $header['origin'] = trim((string) ($bill->origin ?? '')) ?: trim((string) ($voyage->origin ?? ''));
        $header['dstcde'] = trim((string) ($bill->dstcde ?? '')) ?: trim((string) ($voyage->dstcde ?? ''));
        $header['vsslcde'] = trim((string) ($bill->vsslcde ?? '')) ?: trim((string) ($voyage->vsslcde ?? ''));
        $header['voytyp'] = trim((string) ($bill->voytyp ?? '')) ?: trim((string) ($voyage->voytype ?? ''));
        $header['boldte'] = $this->dateOrNull($bill->boldte) ?? $this->dateOrNull($voyage->trndte) ?? '';
        $lines = $this->bills->listCargoLines(trim((string) $bill->voynum), trim((string) $bill->docnum))
            ->map(function ($row) {
                $cargo = $row->toCargoArray();
                $van = $cargo['soc'] ? $cargo['socvannum'] : $cargo['vannum'];
                $prefix = $van !== '' ? $this->bills->vanPrefix($van) : '';
                $cargo['van_label'] = $van === '' ? '' : trim($prefix !== '' ? $prefix.'-'.$van : $van);

                return $cargo;
            })
            ->values()
            ->all();

        $extraCharges = [];
        foreach ($this->bills->shownCharges() as $charge) {
            $field = $charge['chargefld'];
            if ($field === 'frghtamt' || $field === 'vatamt') {
                continue;
            }
            $amount = Amount::parse($bill->{$field} ?? 0);
            if ($amount > 0) {
                $extraCharges[] = [
                    'label' => rtrim($charge['chargedesc'], ' :'),
                    'amount' => Amount::format($amount),
                ];
            }
        }

        return [
            'voyage' => $voyage->toListArray(),
            'header' => $header,
            'lines' => $lines,
            'extra_charges' => $extraCharges,
        ];
    }

    private function cargoLines(mixed $lines): array
    {
        if (! is_array($lines)) {
            return [];
        }

        $kept = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $catcde = trim((string) ($line['catcde'] ?? ''));
            $classdsc = trim((string) ($line['classdsc'] ?? ''));
            if ($catcde === '' && $classdsc === '') {
                continue;
            }
            $kept[] = $line;
        }

        return $kept;
    }

    private function vanIsFree(Voyage $voyage): string
    {
        $voytype = trim((string) ($voyage->voytype ?? ''));
        $origin = trim((string) ($voyage->origin ?? ''));
        $dstcde = trim((string) ($voyage->dstcde ?? ''));
        if ($voytype === 'Reg' && $origin === $dstcde) {
            return '0';
        }

        return '1';
    }

    private function deleteTwinDocuments(string $voynum, string $docnum): void
    {
        $suffix = substr($docnum, -3);
        $twin = $this->duplicateVoyageId($voynum);
        if ($this->bills->voyageExists($twin)) {
            $twinDoc = preg_replace('/[^a-z0-9]/i', '', $twin).$suffix;
            $this->bills->unlockSealsForDocument($twin, $twinDoc);
            $this->bills->deleteHeaderByDocnum($twinDoc);
            $this->bills->deleteLines($twin, $twinDoc);
        }

        $main = $this->stripCf($voynum);
        if ($main !== $voynum && $this->bills->voyageExists($main)) {
            $mainDoc = preg_replace('/[^a-z0-9]/i', '', $main).$suffix;
            $this->bills->unlockSealsForDocument($main, $mainDoc);
            $this->bills->deleteHeaderByDocnum($mainDoc);
            $this->bills->deleteLines($main, $mainDoc);
        }
    }

    private function duplicateVoyageId(string $original): string
    {
        if (str_contains($original, '-')) {
            $parts = explode('-', $original);
            $parts[0] = $parts[0].'CF';

            return implode('-', $parts);
        }

        $withCf = preg_replace('/^([a-zA-Z]+)/', '$1CF', $original);
        if (! is_string($withCf) || $withCf === $original) {
            return $original.'CF';
        }

        return $withCf;
    }

    private function stripCf(string $original): string
    {
        if (str_contains($original, '-')) {
            $parts = explode('-', $original, 2);
            $parts[0] = (string) preg_replace('/CF$/i', '', $parts[0]);

            return implode('-', $parts);
        }

        $stripped = preg_replace('/^([a-zA-Z]+)CF/i', '$1', $original);

        return is_string($stripped) ? $stripped : $original;
    }

    private function dateOrNull(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return null;
        }

        return substr($text, 0, 10);
    }
}
