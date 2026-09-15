<?php

namespace App\Repositories;

use App\Models\ArReceipt;
use App\Models\ArReceiptPayment;
use App\Support\CrudList;
use App\Support\MenuPermission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ArReceiptRepository
{
    /**
     * @return LengthAwarePaginator<int, ArReceipt>
     */
    public function paginateForList(string $search, int $perPage, string $sort = '', string $dir = 'asc'): LengthAwarePaginator
    {
        return $this->filteredQuery($search, $sort, $dir)->paginate($perPage);
    }

    /**
     * @return Collection<int, ArReceipt>
     */
    public function getForList(string $search, string $sort = '', string $dir = 'asc'): Collection
    {
        return $this->filteredQuery($search, $sort, $dir)->get();
    }

    public function findByDocnum(string $docnum): ?ArReceipt
    {
        return ArReceipt::query()->api()->where('docnum', $docnum)->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ArReceipt
    {
        $receipt = new ArReceipt;
        $receipt->fill($attributes);
        $receipt->save();

        return ArReceipt::query()->api()->where('recid', $receipt->recid)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(ArReceipt $receipt, array $attributes): ArReceipt
    {
        $receipt->fill($attributes);
        $receipt->save();

        return ArReceipt::query()->api()->where('recid', $receipt->recid)->firstOrFail();
    }

    public function delete(ArReceipt $receipt): void
    {
        $receipt->delete();
    }

    public function hasPayments(string $docnum): bool
    {
        return ArReceiptPayment::query()->api()->where('docnum', $docnum)->exists();
    }

    /**
     * @return Collection<int, ArReceiptPayment>
     */
    public function payments(string $docnum): Collection
    {
        return ArReceiptPayment::query()->api()->where('docnum', $docnum)->orderBy('recid')->get();
    }

    public function paymentByRecid(int $recid): ?ArReceiptPayment
    {
        return ArReceiptPayment::query()->api()->where('recid', $recid)->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createPayment(array $attributes): ArReceiptPayment
    {
        $recid = DB::table('arpaymentsfile2')->insertGetId($attributes, 'recid');

        return ArReceiptPayment::query()->api()->where('recid', $recid)->firstOrFail();
    }

    public function paymentExists(string $docnum, string $paytyp, string $chknum, string $bnkcde): bool
    {
        $query = ArReceiptPayment::query()->api()->where('docnum', $docnum)->where('paytyp', $paytyp);
        $type = strtoupper($paytyp);
        if ($type === 'CHECK') {
            $query->where('chknum', $chknum)->where('bnkcde', $bnkcde);
        } elseif ($type === 'ONLINE') {
            $query->where('bnkcde', $bnkcde);
        }

        return $query->exists();
    }

    public function deletePayment(int $recid): void
    {
        DB::table('arpaymentsfile2')->where('recid', $recid)->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateHeaderByDocnum(string $docnum, array $attributes): void
    {
        DB::table('arpaymentsfile1')->where('docnum', $docnum)->where('trncde', 'ARP')->update($attributes);
    }

    public function incrementHeaderAmounts(string $docnum, float $amount): void
    {
        $amount = round($amount, 2);
        DB::table('arpaymentsfile1')
            ->where('docnum', $docnum)
            ->where('trncde', 'ARP')
            ->update([
                'amount' => DB::raw('amount + ('.$amount.' * currte)'),
                'balance' => DB::raw('balance + ('.$amount.' * currte)'),
                'setbalance' => DB::raw('setbalance + ('.$amount.' * currte)'),
                'amountfor' => DB::raw('amountfor + '.$amount),
                'balancefor' => DB::raw('balancefor + '.$amount),
                'setbalancefor' => DB::raw('setbalancefor + '.$amount),
            ]);
    }

    public function decrementHeaderOnPaymentDelete(string $docnum, float $amount, float $remaining): void
    {
        $amount = round($amount, 2);
        $remaining = round($remaining, 2);
        DB::table('arpaymentsfile1')
            ->where('docnum', $docnum)
            ->where('trncde', 'ARP')
            ->update([
                'amount' => DB::raw('amount - ('.$amount.' * currte)'),
                'balance' => DB::raw('balance - ('.$remaining.' * currte)'),
                'setbalance' => DB::raw('setbalance - ('.$remaining.' * currte)'),
                'amountfor' => DB::raw('amountfor - '.$amount),
                'balancefor' => DB::raw('balancefor - '.$remaining),
                'setbalancefor' => DB::raw('setbalancefor - '.$remaining),
                'bolnum' => '',
            ]);
    }

    public function restoreBillOnUnapply(string $docapp, float $amtapp, float $currte): void
    {
        $amtapp = round($amtapp, 2);
        $local = round($amtapp * $currte, 2);
        $attributes = [
            'docbal' => DB::raw('docbal + '.$local),
            'docbalfor' => DB::raw('docbalfor + '.$amtapp),
            'ewtamt' => 0,
            'nonvat' => 0,
        ];
        if (Schema::hasColumn('billofladingfile1', 'setdocbal')) {
            $attributes['setdocbal'] = DB::raw('setdocbal + '.$local);
        }
        if (Schema::hasColumn('billofladingfile1', 'setdocbalfor')) {
            $attributes['setdocbalfor'] = DB::raw('setdocbalfor + '.$amtapp);
        }

        DB::table('billofladingfile1')->where('docapp', $docapp)->update($attributes);
    }

    public function companyName(): string
    {
        $row = DB::table('companyfile')->first();
        if (! $row) {
            return '';
        }
        if (isset($row->comdsc) && trim((string) $row->comdsc) !== '') {
            return (string) $row->comdsc;
        }

        return (string) ($row->companydescription ?? '');
    }

    /**
     * @return array{name: string, address: string}
     */
    public function payerProfile(string $payee, string $cuscde, string $concde): array
    {
        if (strtolower($payee) === 'consignee') {
            $row = DB::table('consigneefile')->where('concde', $concde)->first();

            return [
                'name' => trim((string) ($row->condsc ?? '')),
                'address' => trim((string) ($row->conadd1 ?? $row->telnum ?? '')),
            ];
        }

        $row = DB::table('customerfile')->where('cuscde', $cuscde)->first();

        return [
            'name' => trim((string) ($row->cusdsc ?? '')),
            'address' => trim((string) ($row->cusadd1 ?? $row->telno ?? '')),
        ];
    }

    /**
     * @return list<array{bnkcde: string, paytyp: string, chkdte: string, chknum: string}>
     */
    public function pdfPayments(string $docnum): array
    {
        return DB::table('arpaymentsfile2')
            ->where('docnum', $docnum)
            ->get(['bnkcde', 'paytyp', 'chkdte', 'chknum'])
            ->map(fn ($row) => [
                'bnkcde' => trim((string) ($row->bnkcde ?? '')),
                'paytyp' => trim((string) ($row->paytyp ?? '')),
                'chkdte' => substr(trim((string) ($row->chkdte ?? '')), 0, 10),
                'chknum' => trim((string) ($row->chknum ?? '')),
            ])
            ->all();
    }

    /**
     * @return list<array{docapp: string, amtappfor: float, docnum: string, voynum: string, ewtamt: float, vatable: float, vatamt: float, nonvat: mixed}>
     */
    public function pdfApplicationLines(string $docnum): array
    {
        return DB::table('arpaymentapplication as a')
            ->join('billofladingfile1 as b', 'a.docapp', '=', 'b.docapp')
            ->where('a.docnum', $docnum)
            ->where('a.amtappfor', '>', 0)
            ->orderBy('a.recid')
            ->get([
                'a.docapp',
                'a.amtappfor',
                'b.docnum',
                'b.voynum',
                'b.ewtamt',
                'b.vatable',
                'b.vatamt',
                'b.nonvat',
            ])
            ->map(fn ($row) => [
                'docapp' => trim((string) ($row->docapp ?? '')),
                'amtappfor' => (float) ($row->amtappfor ?? 0),
                'docnum' => trim((string) ($row->docnum ?? '')),
                'voynum' => trim((string) ($row->voynum ?? '')),
                'ewtamt' => (float) ($row->ewtamt ?? 0),
                'vatable' => (float) ($row->vatable ?? 0),
                'vatamt' => (float) ($row->vatamt ?? 0),
                'nonvat' => $row->nonvat,
            ])
            ->all();
    }

    /**
     * @return array{witvat: float, vatable: float, vat: float}
     */
    public function pdfVatTotals(string $docnum): array
    {
        $witvat = 0.0;
        $withholding = DB::table('arpaymentapplication as a')
            ->join('billofladingfile1 as b', function ($join) {
                $join->on('a.docapp', '=', 'b.docapp')
                    ->where(function ($inner) {
                        $inner->where('b.ewtamt', '>', 0)->orWhere('b.evatamt', '>', 0);
                    });
            })
            ->where('a.docnum', $docnum)
            ->where('a.amtappfor', '>', 0)
            ->get(['a.ewtamt', 'a.evatamt', 'b.ewtamt as b_ewtamt', 'b.evatamt as b_evatamt']);

        foreach ($withholding as $row) {
            if ((float) ($row->b_ewtamt ?? 0) > 0) {
                $witvat += (float) ($row->ewtamt ?? 0);
            }
            if ((float) ($row->b_evatamt ?? 0) > 0) {
                $witvat += (float) ($row->evatamt ?? 0);
            }
        }

        $vatRows = DB::table('arpaymentapplication as a')
            ->join('billofladingfile1 as b', 'a.docapp', '=', 'b.docapp')
            ->where('a.docnum', $docnum)
            ->where('a.amtappfor', '>', 0)
            ->get(['a.vatable', 'a.vatamt']);

        return [
            'witvat' => round($witvat, 2),
            'vatable' => round((float) $vatRows->sum('vatable'), 2),
            'vat' => round((float) $vatRows->sum('vatamt'), 2),
        ];
    }

    /**
     * @return list<array{paytyp: string}>
     */
    public function paymentTypes(): array
    {
        if (! Schema::hasTable('paymenttypefile')) {
            return [
                ['paytyp' => 'CASH'],
                ['paytyp' => 'CHECK'],
                ['paytyp' => 'ONLINE'],
                ['paytyp' => 'OTHERS'],
            ];
        }

        return DB::table('paymenttypefile')
            ->orderBy('paytyp')
            ->pluck('paytyp')
            ->filter()
            ->map(fn ($value) => ['paytyp' => trim((string) $value)])
            ->values()
            ->all();
    }

    /**
     * @return list<array{bnkcde: string, bnkdsc: string}>
     */
    public function banks(): array
    {
        return DB::table('bankfile')
            ->orderBy('bnkcde')
            ->get(['bnkcde', 'bnkdsc'])
            ->map(fn ($row) => [
                'bnkcde' => trim((string) ($row->bnkcde ?? '')),
                'bnkdsc' => trim((string) ($row->bnkdsc ?? '')),
            ])
            ->filter(fn ($row) => $row['bnkcde'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public function searchPayers(string $type, string $term): array
    {
        $like = '%'.$term.'%';
        if ($type === 'consignee') {
            return DB::table('consigneefile')
                ->where('condsc', 'like', $like)
                ->orderBy('condsc')
                ->limit(8)
                ->get(['concde', 'condsc'])
                ->map(fn ($row) => [
                    'code' => trim((string) ($row->concde ?? '')),
                    'label' => trim((string) ($row->condsc ?? '')),
                ])
                ->values()
                ->all();
        }

        return DB::table('customerfile')
            ->where('cusdsc', 'like', $like)
            ->orderBy('cusdsc')
            ->limit(8)
            ->get(['cuscde', 'cusdsc'])
            ->map(fn ($row) => [
                'code' => trim((string) ($row->cuscde ?? '')),
                'label' => trim((string) ($row->cusdsc ?? '')),
            ])
            ->values()
            ->all();
    }

    /**
     * @return object{shiftcde: mixed, shiftdate: mixed, branchcde: mixed}|null
     */
    public function branchShift(string $branch): ?object
    {
        if (! Schema::hasTable('shift_branchfile') || $branch === '') {
            return null;
        }

        return DB::table('shift_branchfile')->where('branchcde', $branch)->first();
    }

    /**
     * @return array{htop: string, hleft: string, dtop: string, dleft: string}
     */
    public function printOffsets(): array
    {
        $empty = ['htop' => '0', 'hleft' => '0', 'dtop' => '0', 'dleft' => '0'];
        if (! Schema::hasTable('syspar')) {
            return $empty;
        }

        $row = DB::table('syspar')->first(['htop', 'hleft', 'dtop', 'dleft']);
        if (! $row) {
            return $empty;
        }

        return [
            'htop' => (string) ($row->htop ?? '0'),
            'hleft' => (string) ($row->hleft ?? '0'),
            'dtop' => (string) ($row->dtop ?? '0'),
            'dleft' => (string) ($row->dleft ?? '0'),
        ];
    }

    /**
     * @return array{allow_add: bool, allow_edit: bool, allow_delete: bool, allow_view: bool, allow_print: bool}|null
     */
    public function menuPermissions(string $usrcde): ?array
    {
        return MenuPermission::forUserCode($usrcde, 'view_receipt_col.php');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openBills(string $payeeField, string $payeeCode, string $payeeType): array
    {
        return DB::table('billofladingfile1')
            ->select([
                'recid', 'voynum', 'docnum', 'trndte', 'docapp', 'refnum',
                'trntot', 'trntotfor', 'docbal', 'docbalfor', 'duedate',
                'nonvat', 'ewtamt', 'evatamt', 'currte', 'curcde',
            ])
            ->where($payeeField, $payeeCode)
            ->where('payee', $payeeType)
            ->where('docbalfor', '<>', 0)
            ->whereNotNull('docbalfor')
            ->orderBy('docbalfor')
            ->orderBy('docnum')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return list<array{docnum: string}>
     */
    public function billsForWeightCharge(string $payeeField, string $payeeCode, string $payeeType): array
    {
        return DB::table('billofladingfile1')
            ->where($payeeField, $payeeCode)
            ->where('payee', $payeeType)
            ->orderBy('docbalfor')
            ->orderBy('trndte')
            ->orderBy('docnum')
            ->pluck('docnum')
            ->filter()
            ->map(fn ($value) => ['docnum' => trim((string) $value)])
            ->values()
            ->all();
    }

    public function appliedAmount(string $docnum, string $docapp, string $chknum, string $bnkcde, string $paytyp): float
    {
        $query = DB::table('arpaymentapplication')
            ->where('docnum', $docnum)
            ->where('trncde', 'ARP')
            ->where('docapp', $docapp)
            ->where('bnkcde', $bnkcde);

        if (strtoupper($paytyp) === 'ONLINE') {
            $query->where(function ($inner) {
                $inner->whereNull('chknum')->orWhere('chknum', '');
            });
        } else {
            $query->where('chknum', $chknum);
        }

        return (float) $query->value('amtapp');
    }

    /**
     * @return list<array{docapp: string, amtapp: float}>
     */
    public function applicationsForPayment(string $docnum, string $chknum, string $bnkcde, string $paytyp): array
    {
        $query = DB::table('arpaymentapplication')
            ->where('docnum', $docnum)
            ->where('trncde', 'ARP')
            ->where('bnkcde', $bnkcde);

        if (strtoupper($paytyp) === 'ONLINE') {
            $query->where(function ($inner) {
                $inner->whereNull('chknum')->orWhere('chknum', '');
            });
        } else {
            $query->where('chknum', $chknum);
        }

        return $query->get(['docapp', 'amtapp'])
            ->map(fn ($row) => [
                'docapp' => trim((string) ($row->docapp ?? '')),
                'amtapp' => (float) ($row->amtapp ?? 0),
            ])
            ->all();
    }

    public function addPaymentBalance(string $docnum, string $chknum, string $bnkcde, string $paytyp, float $amount): void
    {
        $query = DB::table('arpaymentsfile2')
            ->where('docnum', $docnum)
            ->where('trncde', 'ARP')
            ->where('bnkcde', $bnkcde);

        if (strtoupper($paytyp) === 'ONLINE') {
            $query->where(function ($inner) {
                $inner->whereNull('chknum')->orWhere('chknum', '');
            });
        } else {
            $query->where('chknum', $chknum);
        }

        $query->update([
            'balancefor' => DB::raw('balancefor + '.$amount),
            'balance' => DB::raw('balance + ('.$amount.' * currte)'),
        ]);
    }

    public function deleteApplication(string $docnum, string $docapp, string $chknum, string $bnkcde, string $paytyp): void
    {
        $query = DB::table('arpaymentapplication')
            ->where('docnum', $docnum)
            ->where('trncde', 'ARP')
            ->where('bnkcde', $bnkcde)
            ->where('docapp', $docapp);

        if (strtoupper($paytyp) === 'ONLINE') {
            $query->where(function ($inner) {
                $inner->whereNull('chknum')->orWhere('chknum', '');
            });
        } else {
            $query->where('chknum', $chknum);
        }

        $query->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertApplication(array $attributes, string $paytyp): void
    {
        $query = DB::table('arpaymentapplication')
            ->where('docnum', $attributes['docnum'])
            ->where('trncde', $attributes['trncde'])
            ->where('bnkcde', $attributes['bnkcde'])
            ->where('docapp', $attributes['docapp']);

        if (strtoupper($paytyp) === 'ONLINE') {
            $query->where(function ($inner) {
                $inner->whereNull('chknum')->orWhere('chknum', '');
            });
        } else {
            $query->where('chknum', $attributes['chknum'] ?? '');
        }

        $existing = $query->first();
        if ($existing) {
            $query->update($attributes);

            return;
        }

        DB::table('arpaymentapplication')->insert($attributes);
    }

    public function deleteApplicationsForPayment(string $docnum, string $chknum, string $bnkcde, string $paytyp): void
    {
        $query = DB::table('arpaymentapplication')
            ->where('docnum', $docnum)
            ->where('trncde', 'ARP')
            ->where('bnkcde', $bnkcde);

        if (strtoupper($paytyp) === 'ONLINE') {
            $query->where(function ($inner) {
                $inner->whereNull('chknum')->orWhere('chknum', '');
            });
        } else {
            $query->where('chknum', $chknum);
        }

        $query->delete();
    }

    /**
     * @return list<string>
     */
    public function applicationDocapps(string $docnum, string $chknum, string $bnkcde, string $paytyp): array
    {
        $query = DB::table('arpaymentapplication')
            ->where('docnum', $docnum)
            ->where('trncde', 'ARP')
            ->where('bnkcde', $bnkcde);

        if (strtoupper($paytyp) === 'ONLINE') {
            $query->where(function ($inner) {
                $inner->whereNull('chknum')->orWhere('chknum', '');
            });
        } else {
            $query->where('chknum', $chknum);
        }

        return $query->pluck('docapp')->map(fn ($value) => trim((string) $value))->filter()->values()->all();
    }

    public function appliedTotalForPayment(string $docnum, string $chknum, string $bnkcde, string $paytyp): float
    {
        $query = DB::table('arpaymentapplication')
            ->where('docnum', $docnum)
            ->where('trncde', 'ARP')
            ->where('bnkcde', $bnkcde);

        if (strtoupper($paytyp) === 'ONLINE') {
            $query->where(function ($inner) {
                $inner->whereNull('chknum')->orWhere('chknum', '');
            });
        } else {
            $query->where('chknum', $chknum);
        }

        return round((float) $query->sum('amtapp'), 2);
    }

    public function updatePaymentBalances(int $recid, float $applied): void
    {
        DB::table('arpaymentsfile2')->where('recid', $recid)->update([
            'balance' => DB::raw('round(amount - ('.$applied.' * currte), 2)'),
            'balancefor' => DB::raw('round(amount - '.$applied.', 2)'),
        ]);
    }

    public function recomputeHeaderBalance(string $docnum): void
    {
        $applied = 0.0;
        foreach (DB::table('arpaymentsfile2')->where('docnum', $docnum)->get(['amount', 'balance']) as $row) {
            $applied += round(((float) $row->amount) - ((float) $row->balance), 2);
        }
        $applied = round($applied, 2);

        DB::table('arpaymentsfile1')
            ->where('docnum', $docnum)
            ->where('trncde', 'ARP')
            ->update([
                'balance' => DB::raw('round(amount - ('.$applied.' * currte), 2)'),
                'balancefor' => DB::raw('round(amount - '.$applied.', 2)'),
                'bolnum' => '',
            ]);
    }

    public function billByRecid(int $recid): ?object
    {
        return DB::table('billofladingfile1')->where('recid', $recid)->first();
    }

    public function appliedToDocapp(string $docapp): array
    {
        $rows = DB::table('arpaymentapplication')->where('docapp', $docapp)->get(['amtapp', 'amtappfor']);

        return [
            'amtapp' => (float) $rows->sum('amtapp'),
            'amtappfor' => (float) $rows->sum('amtappfor'),
        ];
    }

    public function updateBillBalance(int $recid, float $applied, float $ewt, float $evat, float $vatable, float $vatamt, int $nonvat): void
    {
        DB::table('billofladingfile1')->where('recid', $recid)->update([
            'docbal' => DB::raw('round(trntot - ('.$applied.' * currte), 2)'),
            'docbalfor' => DB::raw('round(trntot - '.$applied.', 2)'),
            'ewtamt' => $ewt,
            'evatamt' => $evat,
            'vatable' => $vatable,
            'vatamt' => $vatamt,
            'nonvat' => $nonvat,
        ]);
    }

    public function updateBillBalanceByDocapp(string $docapp, float $applied, float $appliedFor): void
    {
        DB::table('billofladingfile1')->where('docapp', $docapp)->update([
            'docbal' => DB::raw('round(trntot - '.$applied.', 2)'),
            'docbalfor' => DB::raw('round(trntotfor - '.$appliedFor.', 2)'),
        ]);
    }

    /**
     * @param  list<string>  $docapps
     */
    public function recomputePastBills(array $docapps): void
    {
        foreach (array_unique(array_filter($docapps)) as $docapp) {
            $totals = $this->appliedToDocapp((string) $docapp);
            $this->updateBillBalanceByDocapp((string) $docapp, (float) $totals['amtapp'], (float) $totals['amtappfor']);
        }
    }

    public function markPrinted(int $recid): void
    {
        DB::table('arpaymentsfile1')->where('recid', $recid)->update(['is_print' => 1]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<ArReceipt>
     */
    private function filteredQuery(string $search, string $sort = '', string $dir = 'asc')
    {
        [$column, $direction] = CrudList::order(
            $sort,
            $dir,
            ['docnum', 'trndte', 'payee', 'payeedsc', 'amountfor', 'balancefor', 'cancelled'],
            'docnum',
            'desc',
        );
        $query = ArReceipt::query()->api()->orderBy($column, $direction);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('docnum', 'like', $like)
                    ->orWhere('payeedsc', 'like', $like)
                    ->orWhere('payee', 'like', $like)
                    ->orWhere('cusdsc', 'like', $like)
                    ->orWhere('condsc', 'like', $like);
            });
        }

        return $query;
    }
}
