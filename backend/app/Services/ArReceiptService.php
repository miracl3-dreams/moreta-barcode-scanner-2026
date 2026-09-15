<?php

namespace App\Services;

use App\Models\ArReceipt;
use App\Models\ArReceiptPayment;
use App\Models\User;
use App\Repositories\ArReceiptRepository;
use App\Support\ArReceiptPdf;
use App\Support\CrudList;
use App\Support\MenuPermission;
use App\Support\Text;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ArReceiptService
{
    private const MENPRG = 'view_receipt_col.php';

    public function __construct(private ArReceiptRepository $receipts) {}

    /**
     * @return array{
     *     shiftcde: string,
     *     payment_types: list<array{paytyp: string}>,
     *     banks: list<array{bnkcde: string, bnkdsc: string}>,
     *     print_offsets: array{htop: string, hleft: string, dtop: string, dleft: string},
     *     permissions: array{can_add: bool, can_edit: bool, can_delete: bool, can_view: bool, can_print: bool}
     * }
     */
    public function lookups(User $user): array
    {
        $shift = $this->receipts->branchShift(trim((string) ($user->brnchcde ?? '')));

        return [
            'shiftcde' => trim((string) ($shift->shiftcde ?? '')),
            'payment_types' => $this->receipts->paymentTypes(),
            'banks' => $this->receipts->banks(),
            'print_offsets' => $this->receipts->printOffsets(),
            'permissions' => $this->permissionsFor($user),
        ];
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public function searchPayers(string $type, string $term): array
    {
        $kind = strtolower(trim($type)) === 'consignee' ? 'consignee' : 'shipper';

        return $this->receipts->searchPayers($kind, Text::clip($term, 100));
    }

    /**
     * @return array{data: mixed, meta: array<string, int>}
     */
    public function list(string $search, mixed $perPage, string $sort = '', string $dir = 'asc'): array
    {
        $pageSize = CrudList::perPage($perPage);
        if ($pageSize === 'all') {
            return CrudList::fromCollection($this->receipts->getForList($search, $sort, $dir));
        }

        return CrudList::fromPaginator($this->receipts->paginateForList($search, $pageSize, $sort, $dir));
    }

    /**
     * @return array{receipt: array<string, mixed>, payments: list<array<string, mixed>>, bills: list<array{docnum: string}>}
     */
    public function show(ArReceipt $receipt): array
    {
        $docnum = trim((string) ($receipt->docnum ?? ''));
        $payee = strtolower(trim((string) ($receipt->payee ?? 'shipper')));
        $field = $payee === 'consignee' ? 'concde' : 'cuscde';
        $code = $payee === 'consignee'
            ? trim((string) ($receipt->concde ?? ''))
            : trim((string) ($receipt->cuscde ?? ''));

        return [
            'receipt' => $receipt->toDetailArray(),
            'payments' => $this->receipts->payments($docnum)->map(fn ($row) => $row->toListArray())->values()->all(),
            'bills' => $code === '' ? [] : $this->receipts->billsForWeightCharge($field, $code, $payee),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{receipt: array<string, mixed>, payments: list<array<string, mixed>>, bills: list<array{docnum: string}>}
     */
    public function create(array $validated, User $user): array
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_ADD);

        $docnum = Text::clip($validated['docnum'] ?? '', 15);
        if ($this->receipts->findByDocnum($docnum) !== null) {
            throw ValidationException::withMessages([
                'docnum' => ['Duplicate OR. Number'],
            ]);
        }

        $branch = trim((string) ($user->brnchcde ?? ''));
        $shift = $this->receipts->branchShift($branch);
        if ($shift === null) {
            throw ValidationException::withMessages([
                'shiftcde' => ['No current shift for this branch.'],
            ]);
        }

        $payee = strtolower(trim((string) ($validated['payee'] ?? 'shipper'))) === 'consignee'
            ? 'consignee'
            : 'shipper';
        $payerName = Text::clip($validated['payer_name'] ?? '', 100);
        $payerCode = Text::clip($validated['payer_code'] ?? '', 30);
        $now = now('Asia/Manila');

        $attributes = [
            'docnum' => $docnum,
            'trndte' => $now->format('Y-m-d H:i:s'),
            'payee' => $payee,
            'payeedsc' => $payerName,
            'cuscde' => $payee === 'shipper' ? $payerCode : '',
            'cusdsc' => $payee === 'shipper' ? $payerName : '',
            'concde' => $payee === 'consignee' ? $payerCode : '',
            'condsc' => $payee === 'consignee' ? $payerName : '',
            'trncde' => 'ARP',
            'curcde' => 'PHP',
            'currte' => 1,
            'shiftcde' => Text::clip($shift->shiftcde ?? '', 50),
            'shiftdte' => substr((string) ($shift->shiftdate ?? ''), 0, 10),
            'usrcde' => Text::clip($user->usrcde ?? '', 50),
            'brnchcde' => Text::clip($shift->branchcde ?? $branch, 50),
            'amount' => 0,
            'amountfor' => 0,
            'balance' => 0,
            'balancefor' => 0,
            'setbalance' => 0,
            'setbalancefor' => 0,
            'cancelled' => '',
            'is_print' => 0,
            'dirpay' => 0,
            'bolnum' => '',
        ];
        if (Schema::hasColumn('arpaymentsfile1', 'usrnam')) {
            $attributes['usrnam'] = Text::clip($user->usrname ?? $user->usrcde ?? '', 50);
        }

        $created = $this->receipts->create($attributes);

        return $this->show($created);
    }

    public function delete(ArReceipt $receipt, User $user): void
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_DELETE);

        $docnum = trim((string) ($receipt->docnum ?? ''));
        if ($docnum !== '' && $this->receipts->hasPayments($docnum)) {
            throw ValidationException::withMessages([
                'docnum' => ["This document already have payments. Can't delete."],
            ]);
        }

        $this->receipts->delete($receipt);
    }

    /**
     * @return array{receipt: array<string, mixed>, payments: list<array<string, mixed>>, bills: list<array{docnum: string}>}
     */
    public function cancel(ArReceipt $receipt, User $user): array
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_CANCEL);

        $this->assertEditable($receipt);
        $docnum = trim((string) ($receipt->docnum ?? ''));

        DB::transaction(function () use ($receipt, $docnum) {
            foreach ($this->receipts->payments($docnum) as $payment) {
                $this->removePayment($receipt, $payment, false);
            }
            $this->receipts->save($receipt, ['cancelled' => 'CANCELLED']);
        });

        $fresh = ArReceipt::query()->api()->where('recid', $receipt->recid)->firstOrFail();

        return $this->show($fresh);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{receipt: array<string, mixed>, payments: list<array<string, mixed>>, bills: list<array{docnum: string}>}
     */
    public function saveBolnum(ArReceipt $receipt, array $validated, User $user): array
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_EDIT);

        $this->assertEditable($receipt);
        $this->receipts->save($receipt, [
            'bolnum' => Text::clip($validated['bolnum'] ?? '', 100),
        ]);

        $fresh = ArReceipt::query()->api()->where('recid', $receipt->recid)->firstOrFail();

        return $this->show($fresh);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{receipt: array<string, mixed>, payments: list<array<string, mixed>>, bills: list<array{docnum: string}>}
     */
    public function addPayment(ArReceipt $receipt, array $validated, User $user): array
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_EDIT);

        $this->assertEditable($receipt);

        $paytyp = strtoupper(Text::clip($validated['paytyp'] ?? '', 30));
        $bnkcde = Text::clip($validated['bnkcde'] ?? '', 25);
        $bnkdsc = Text::clip($validated['bnkdsc'] ?? '', 30);
        $chknum = Text::clip($validated['chknum'] ?? '', 25);
        $refnum = Text::clip($validated['refnum'] ?? '', 50);
        $chkdte = trim((string) ($validated['chkdte'] ?? ''));
        $amount = round((float) ($validated['amount'] ?? 0), 2);
        $docnum = trim((string) ($receipt->docnum ?? ''));

        if ($paytyp === '') {
            throw ValidationException::withMessages(['paytyp' => ['Pls. select Payment type.']]);
        }
        if ($this->receipts->paymentExists($docnum, $paytyp, $chknum, $bnkcde)) {
            throw ValidationException::withMessages(['paytyp' => ['Payment already exist...']]);
        }
        if (in_array($paytyp, ['CHECK', 'ONLINE'], true) && $bnkcde === '') {
            throw ValidationException::withMessages(['bnkcde' => ['Bank Account should not be blank!']]);
        }
        if ($paytyp === 'CHECK' && $chknum === '') {
            throw ValidationException::withMessages(['chknum' => ['Check No. should not be blank!']]);
        }
        if (in_array($paytyp, ['CHECK', 'ONLINE'], true) && $chkdte === '') {
            throw ValidationException::withMessages(['chkdte' => ['Pls. enter Check Date.']]);
        }
        if ($amount == 0.0) {
            throw ValidationException::withMessages(['amount' => ['Must enter amount.']]);
        }

        $headerDate = substr((string) ($receipt->trndte ?? ''), 0, 19);
        if ($paytyp === 'CASH') {
            $bnkcde = 'NONE';
            $chknum = 'CASH';
            $chkdte = $headerDate;
        } elseif ($paytyp === 'ONLINE') {
            $chknum = '';
            if ($chkdte === '') {
                $chkdte = $headerDate;
            }
        } elseif ($paytyp === 'OTHERS') {
            $bnkcde = 'NONE';
            $chknum = 'NONE';
            if ($chkdte === '') {
                $chkdte = $headerDate;
            }
        }

        $chkdte = $this->normalizeDate($chkdte);
        $rate = (float) ($receipt->currte ?? 1);
        if ($rate <= 0) {
            $rate = 1;
        }
        $local = round($amount * $rate, 2);

        DB::transaction(function () use ($receipt, $user, $docnum, $paytyp, $bnkcde, $bnkdsc, $chknum, $refnum, $chkdte, $amount, $local, $headerDate, $validated) {
            $row = [
                'docnum' => $docnum,
                'trncde' => 'ARP',
                'paytyp' => $paytyp,
                'bnkcde' => $bnkcde,
                'bnkdsc' => $bnkdsc,
                'chknum' => $chknum,
                'refnum' => $refnum,
                'chkdte' => $chkdte,
                'amount' => $local,
                'balance' => $local,
                'setbalance' => $local,
                'amountfor' => $amount,
                'balancefor' => $amount,
                'setbalancefor' => $amount,
                'grossamt' => $local,
                'grossamtfor' => $amount,
                'cuscde' => $receipt->cuscde,
                'cusdsc' => $receipt->cusdsc,
                'concde' => $receipt->concde,
                'condsc' => $receipt->condsc,
                'payee' => $receipt->payee,
                'curcde' => $receipt->curcde,
                'currte' => $receipt->currte,
                'usrnam' => Text::clip($user->usrcde ?? '', 50),
            ];
            if (Schema::hasColumn('arpaymentsfile2', 'trndte')) {
                $row['trndte'] = $headerDate;
            }
            if (Schema::hasColumn('arpaymentsfile2', 'logdte')) {
                $row['logdte'] = $chkdte;
            }
            if (Schema::hasColumn('arpaymentsfile2', 'memtypcde')) {
                $row['memtypcde'] = Text::clip($validated['memtypcde'] ?? '', 30);
            }

            $this->receipts->createPayment($row);
            $this->receipts->incrementHeaderAmounts($docnum, $amount);
        });

        $fresh = ArReceipt::query()->api()->where('recid', $receipt->recid)->firstOrFail();

        return $this->show($fresh);
    }

    /**
     * @return array{receipt: array<string, mixed>, payments: list<array<string, mixed>>, bills: list<array{docnum: string}>}
     */
    public function deletePayment(ArReceipt $receipt, int $paymentRecid, User $user): array
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_EDIT);

        $this->assertEditable($receipt);
        $payment = $this->paymentOnReceipt($receipt, $paymentRecid);

        DB::transaction(function () use ($receipt, $payment) {
            $this->removePayment($receipt, $payment, true);
        });

        $fresh = ArReceipt::query()->api()->where('recid', $receipt->recid)->firstOrFail();

        return $this->show($fresh);
    }

    /**
     * @return array{
     *     payment: array<string, mixed>,
     *     lines: list<array<string, mixed>>
     * }
     */
    public function applications(ArReceipt $receipt, int $paymentRecid): array
    {
        $payment = $this->paymentOnReceipt($receipt, $paymentRecid);
        $payee = strtolower(trim((string) ($receipt->payee ?? 'shipper')));
        $field = $payee === 'consignee' ? 'concde' : 'cuscde';
        $code = $payee === 'consignee'
            ? trim((string) ($receipt->concde ?? ''))
            : trim((string) ($receipt->cuscde ?? ''));

        $lines = [];
        foreach ($this->receipts->openBills($field, $code, $payee) as $bill) {
            $docapp = trim((string) ($bill['docapp'] ?? ''));
            $applied = $this->receipts->appliedAmount(
                trim((string) ($receipt->docnum ?? '')),
                $docapp,
                trim((string) ($payment->chknum ?? '')),
                trim((string) ($payment->bnkcde ?? '')),
                trim((string) ($payment->paytyp ?? '')),
            );
            $lines[] = [
                'recid' => (int) ($bill['recid'] ?? 0),
                'voynum' => trim((string) ($bill['voynum'] ?? '')),
                'docnum' => trim((string) ($bill['docnum'] ?? '')),
                'docapp' => $docapp,
                'trntot' => $this->money($bill['trntotfor'] ?? $bill['trntot'] ?? 0),
                'docbal' => $this->money($bill['docbalfor'] ?? $bill['docbal'] ?? 0),
                'amtapp' => $this->money($applied),
                'ewt' => (float) ($bill['ewtamt'] ?? 0) > 0,
                'evat' => (float) ($bill['evatamt'] ?? 0) > 0,
                'nonvat' => (int) ($bill['nonvat'] ?? 0) === 1,
            ];
        }

        return [
            'payment' => $payment->toListArray(),
            'lines' => $lines,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{receipt: array<string, mixed>, payments: list<array<string, mixed>>, bills: list<array{docnum: string}>}
     */
    public function apply(ArReceipt $receipt, int $paymentRecid, array $validated, User $user): array
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_EDIT);

        $this->assertEditable($receipt);
        $payment = $this->paymentOnReceipt($receipt, $paymentRecid);
        $docnum = trim((string) ($receipt->docnum ?? ''));
        $paytyp = trim((string) ($payment->paytyp ?? ''));
        $chknum = trim((string) ($payment->chknum ?? ''));
        $bnkcde = trim((string) ($payment->bnkcde ?? ''));
        $rate = (float) ($payment->currte ?? 1);
        if ($rate <= 0) {
            $rate = 1;
        }

        /** @var list<array<string, mixed>> $lines */
        $lines = $validated['lines'] ?? [];

        DB::transaction(function () use ($payment, $docnum, $paytyp, $chknum, $bnkcde, $rate, $lines) {
            $existing = $this->receipts->applicationsForPayment($docnum, $chknum, $bnkcde, $paytyp);
            $reversed = [];
            foreach ($existing as $app) {
                $this->receipts->addPaymentBalance($docnum, $chknum, $bnkcde, $paytyp, (float) $app['amtapp']);
                $this->receipts->deleteApplication($docnum, $app['docapp'], $chknum, $bnkcde, $paytyp);
                $reversed[] = $app['docapp'];
            }

            $fresh = ArReceiptPayment::query()->api()->where('recid', $payment->recid)->firstOrFail();
            $remaining = round((float) ($fresh->balancefor ?? $fresh->balance ?? 0), 2);

            foreach ($lines as $line) {
                $amtapp = round((float) ($line['amtapp'] ?? 0), 2);
                if ($remaining < $amtapp) {
                    $amtapp = $remaining;
                }
                $remaining = round($remaining - $amtapp, 2);

                $billRecid = (int) ($line['recid'] ?? 0);
                $bill = $this->receipts->billByRecid($billRecid);
                $docapp = Text::clip($line['docapp'] ?? ($bill?->docapp ?? ''), 50);
                $nonvat = ! empty($line['nonvat']);
                $ewt = ! empty($line['ewt']);
                $evat = ! empty($line['evat']);
                $billRate = $bill !== null ? (float) ($bill->currte ?? $rate) : $rate;
                if ($billRate <= 0) {
                    $billRate = $rate;
                }

                $base = $nonvat ? $amtapp : ($amtapp / 1.12);
                $ewtamt = $ewt ? round($base * 0.02, 2) : 0.0;
                $evatamt = $evat ? round($base * 0.07, 2) : 0.0;
                $vatable = $nonvat ? 0.0 : round($amtapp / 1.12, 2);
                $vatamt = $nonvat ? 0.0 : round($vatable * 0.12, 2);

                $row = [
                    'docnum' => $docnum,
                    'docapp' => $docapp,
                    'ewtamt' => $ewtamt,
                    'evatamt' => $evatamt,
                    'vatable' => $vatable,
                    'vatamt' => $vatamt,
                    'nonvat' => $nonvat ? 1 : 0,
                    'trncde' => 'ARP',
                    'trndte' => $fresh->chkdte ?? $payment->chkdte,
                    'cusdsc' => $fresh->cusdsc,
                    'cuscde' => $fresh->cuscde,
                    'chknum' => $chknum,
                    'amtapp' => round($amtapp * $rate, 2),
                    'bnkcde' => $bnkcde,
                    'curcde' => $fresh->curcde,
                    'currte' => $fresh->currte,
                    'chkdte' => $fresh->chkdte,
                    'amtappfor' => round($amtapp, 2),
                    'docappamtapp' => round($amtapp * $billRate, 2),
                    'forexgl' => round((($amtapp * $billRate) - ($amtapp * $rate)) * -1, 2),
                    'doccurcde' => $bill?->curcde ?? $fresh->curcde,
                    'doccurrte' => $bill?->currte ?? $fresh->currte,
                    'docapptrndte' => $bill?->trndte ?? null,
                    'docapptrntot' => $bill?->trntot ?? 0,
                    'docappduedte' => $bill?->duedate ?? null,
                ];
                if (Schema::hasColumn('arpaymentapplication', 'logdte')) {
                    $row['logdte'] = now('Asia/Manila')->format('Y-m-d H:i:s');
                }
                if (Schema::hasColumn('arpaymentapplication', 'logtim')) {
                    $row['logtim'] = now('Asia/Manila')->format('Y-m-d H:i:s');
                }

                if (round($amtapp, 2) == 0.0) {
                    $this->receipts->deleteApplication($docnum, $docapp, $chknum, $bnkcde, $paytyp);
                } else {
                    $this->receipts->upsertApplication($row, $paytyp);
                }

                $this->recomputeBill($billRecid, $nonvat, $ewt, $evat);
            }

            if ($reversed !== []) {
                $this->receipts->recomputePastBills($reversed);
            }

            $applied = $this->receipts->appliedTotalForPayment($docnum, $chknum, $bnkcde, $paytyp);
            $this->receipts->updatePaymentBalances((int) $payment->recid, $applied);
            $this->receipts->recomputeHeaderBalance($docnum);
        });

        $fresh = ArReceipt::query()->api()->where('recid', $receipt->recid)->firstOrFail();

        return $this->show($fresh);
    }

    public function pdf(ArReceipt $receipt, bool $newFormat, array $offsets = []): Response
    {
        $this->receipts->markPrinted((int) $receipt->recid);
        $payload = $this->pdfPayload($receipt, $newFormat, $offsets);

        return ArReceiptPdf::render($payload);
    }

    /**
     * @return array{can_add: bool, can_edit: bool, can_delete: bool, can_view: bool, can_print: bool}
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
            ];
        }

        $perms = $this->receipts->menuPermissions((string) $user->usrcde);
        if ($perms === null) {
            return [
                'can_add' => false,
                'can_edit' => false,
                'can_delete' => false,
                'can_view' => true,
                'can_print' => false,
            ];
        }

        return [
            'can_add' => $perms['allow_add'],
            'can_edit' => $perms['allow_edit'],
            'can_delete' => $perms['allow_delete'],
            'can_view' => $perms['allow_view'],
            'can_print' => $perms['allow_print'],
        ];
    }

    private function assertEditable(ArReceipt $receipt): void
    {
        if (strtoupper(trim((string) ($receipt->cancelled ?? ''))) === 'CANCELLED') {
            throw ValidationException::withMessages([
                'cancelled' => ['This official receipt is cancelled.'],
            ]);
        }
    }

    private function paymentOnReceipt(ArReceipt $receipt, int $paymentRecid): ArReceiptPayment
    {
        $payment = $this->receipts->paymentByRecid($paymentRecid);
        if ($payment === null || trim((string) $payment->docnum) !== trim((string) $receipt->docnum)) {
            throw ValidationException::withMessages([
                'payment' => ['Payment not found on this receipt.'],
            ]);
        }

        return $payment;
    }

    private function removePayment(ArReceipt $receipt, ArReceiptPayment $payment, bool $updateHeader): void
    {
        $docnum = trim((string) ($payment->docnum ?? ''));
        $paytyp = trim((string) ($payment->paytyp ?? ''));
        $chknum = trim((string) ($payment->chknum ?? ''));
        $bnkcde = trim((string) ($payment->bnkcde ?? ''));
        $amount = round((float) ($payment->amountfor ?? $payment->amount ?? 0), 2);
        $rate = (float) ($payment->currte ?? $receipt->currte ?? 1);
        $remaining = $amount;

        foreach ($this->receipts->applicationsForPayment($docnum, $chknum, $bnkcde, $paytyp) as $app) {
            $remaining = round($remaining - (float) $app['amtapp'], 2);
            $this->receipts->restoreBillOnUnapply($app['docapp'], (float) $app['amtapp'], $rate);
        }
        $this->receipts->deleteApplicationsForPayment($docnum, $chknum, $bnkcde, $paytyp);
        if ($updateHeader) {
            $this->receipts->decrementHeaderOnPaymentDelete($docnum, $amount, $remaining);
        }
        $this->receipts->deletePayment((int) $payment->recid);
    }

    private function recomputeBill(int $billRecid, bool $nonvat, bool $ewt, bool $evat): void
    {
        $bill = $this->receipts->billByRecid($billRecid);
        if ($bill === null) {
            return;
        }

        $trntot = (float) ($bill->trntot ?? 0);
        $base = $nonvat ? $trntot : ($trntot / 1.12);
        $ewtamt = $ewt ? round($base * 0.02, 2) : 0.0;
        $evatamt = $evat ? round($base * 0.07, 2) : 0.0;
        $vatable = $nonvat ? 0.0 : round($trntot / 1.12, 2);
        $vatamt = $nonvat ? 0.0 : round($vatable * 0.12, 2);
        $applied = (float) $this->receipts->appliedToDocapp(trim((string) ($bill->docapp ?? '')))['amtapp'];

        $this->receipts->updateBillBalance(
            $billRecid,
            $applied,
            $ewtamt,
            $evatamt,
            $vatable,
            $vatamt,
            $nonvat ? 1 : 0,
        );
    }

    /**
     * @param  array{htop?: string, hleft?: string, dtop?: string, dleft?: string}  $offsets
     * @return array<string, mixed>
     */
    private function pdfPayload(ArReceipt $receipt, bool $newFormat, array $offsets): array
    {
        $docnum = trim((string) ($receipt->docnum ?? ''));
        $payer = $this->receipts->payerProfile(
            (string) ($receipt->payee ?? ''),
            (string) ($receipt->cuscde ?? ''),
            (string) ($receipt->concde ?? ''),
        );
        $name = $payer['name'] !== '' ? $payer['name'] : trim((string) ($receipt->payeedsc ?? ''));
        $amount = (float) ($receipt->amountfor ?? $receipt->amount ?? 0);
        $lines = $this->receipts->pdfApplicationLines($docnum);
        $applied = 0.0;
        foreach ($lines as $line) {
            $applied += (float) $line['amtappfor'];
        }
        $vat = $this->receipts->pdfVatTotals($docnum);
        $totvat = $vat['witvat'] > 0 ? round($applied - $vat['witvat'], 2) : round($applied, 2);
        $shown = $applied > 0 ? $totvat : round($amount, 2);

        $banks = [];
        $checks = [];
        $dates = [];
        foreach ($this->receipts->pdfPayments($docnum) as $row) {
            if ($row['bnkcde'] !== 'NONE') {
                $banks[] = 'BANK: '.$row['bnkcde'];
                $checks[] = $row['chknum'];
                $dates[] = strtoupper($row['paytyp']) === 'ONLINE' ? 'ONLINE' : $this->displayDate($row['chkdte']);
            } else {
                $banks[] = 'CASH';
            }
        }

        $defaults = $this->receipts->printOffsets();

        return [
            'company' => $this->receipts->companyName(),
            'docnum' => $docnum,
            'date' => now('Asia/Manila')->format('m/d/Y'),
            'payee' => $name,
            'address' => $payer['address'],
            'amount' => $this->money($shown),
            'amount_words' => $this->amountInWords($shown),
            'applied' => $this->money($applied),
            'vatable' => $this->money($vat['vatable']),
            'vat' => $this->money($vat['vat']),
            'witvat' => $this->money($vat['witvat']),
            'banks' => implode(', ', $banks),
            'checks' => implode(', ', $checks),
            'check_dates' => implode(', ', $dates),
            'lines' => $lines,
            'new_format' => $newFormat,
            'htop' => (float) ($offsets['htop'] ?? $defaults['htop']),
            'hleft' => (float) ($offsets['hleft'] ?? $defaults['hleft']),
            'dtop' => (float) ($offsets['dtop'] ?? $defaults['dtop']),
            'dleft' => (float) ($offsets['dleft'] ?? $defaults['dleft']),
        ];
    }

    private function normalizeDate(string $value): string
    {
        $text = trim($value);
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return now('Asia/Manila')->format('Y-m-d');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $text) === 1) {
            return substr($text, 0, 10);
        }
        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})/', $text, $match) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $match[3], (int) $match[1], (int) $match[2]);
        }

        return substr($text, 0, 10);
    }

    private function displayDate(string $value): string
    {
        $iso = $this->normalizeDate($value);
        $parts = explode('-', $iso);
        if (count($parts) !== 3) {
            return $iso;
        }

        return $parts[1].'/'.$parts[2].'/'.$parts[0];
    }

    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    private function amountInWords(float $amount): string
    {
        $pesos = (int) floor(round($amount, 2));
        $cents = (int) round((round($amount, 2) - $pesos) * 100);
        $words = $this->integerInWords($pesos).' PESOS';
        if ($cents > 0) {
            $words .= ' AND '.$this->integerInWords($cents).' CENTAVOS';
        }

        return $words.' ONLY';
    }

    private function integerInWords(int $number): string
    {
        if ($number === 0) {
            return 'ZERO';
        }

        $ones = ['', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN', 'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN', 'SEVENTEEN', 'EIGHTEEN', 'NINETEEN'];
        $tens = ['', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY'];

        $chunk = function (int $n) use ($ones, $tens): string {
            $parts = [];
            if ($n >= 100) {
                $parts[] = $ones[(int) floor($n / 100)].' HUNDRED';
                $n %= 100;
            }
            if ($n >= 20) {
                $parts[] = $tens[(int) floor($n / 10)];
                $n %= 10;
                if ($n > 0) {
                    $parts[] = $ones[$n];
                }
            } elseif ($n > 0) {
                $parts[] = $ones[$n];
            }

            return implode(' ', $parts);
        };

        $scales = [
            1000000000 => 'BILLION',
            1000000 => 'MILLION',
            1000 => 'THOUSAND',
        ];
        $parts = [];
        foreach ($scales as $value => $label) {
            if ($number >= $value) {
                $parts[] = $chunk((int) floor($number / $value)).' '.$label;
                $number %= $value;
            }
        }
        if ($number > 0) {
            $parts[] = $chunk($number);
        }

        return implode(' ', $parts);
    }
}
