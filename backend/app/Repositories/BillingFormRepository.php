<?php

namespace App\Repositories;

use App\Models\BillingForm;
use App\Models\BillingFormLine;
use App\Support\CrudList;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BillingFormRepository
{
    /**
     * @return LengthAwarePaginator<int, BillingForm>
     */
    public function paginateForList(string $cuscde, string $search, int $perPage, string $sort = '', string $dir = 'desc'): LengthAwarePaginator
    {
        return $this->filteredQuery($cuscde, $search, $sort, $dir)->paginate($perPage);
    }

    /**
     * @return Collection<int, BillingForm>
     */
    public function getForList(string $cuscde, string $search, string $sort = '', string $dir = 'desc'): Collection
    {
        return $this->filteredQuery($cuscde, $search, $sort, $dir)->get();
    }

    public function findOwned(int $recid, string $cuscde): ?BillingForm
    {
        return BillingForm::query()
            ->api()
            ->where('recid', $recid)
            ->where('cuscde', $cuscde)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): BillingForm
    {
        $billing = BillingForm::query()->create($attributes);

        return BillingForm::query()->api()->where('recid', $billing->recid)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(BillingForm $billing, array $attributes): BillingForm
    {
        $billing->fill($attributes);
        $billing->save();

        return BillingForm::query()->api()->where('recid', $billing->recid)->firstOrFail();
    }

    public function deleteByDocnum(string $docnum): void
    {
        BillingForm::query()->where('docnum', $docnum)->delete();
        BillingFormLine::query()->where('docnum', $docnum)->delete();
        $this->clearEirBilling($docnum);
    }

    /**
     * @return Collection<int, BillingFormLine>
     */
    public function lines(string $docnum): Collection
    {
        return BillingFormLine::query()
            ->where('docnum', $docnum)
            ->orderBy('recid')
            ->get();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function replaceLines(string $docnum, array $lines): void
    {
        BillingFormLine::query()->where('docnum', $docnum)->delete();
        foreach ($lines as $line) {
            BillingFormLine::query()->create($line);
        }
    }

    public function nextDocnum(): string
    {
        return (string) DB::transaction(function () {
            if (! Schema::hasTable('syspar') || ! Schema::hasColumn('syspar', 'billing_docnum')) {
                return $this->fallbackDocnum();
            }

            $row = DB::table('syspar')->where('recid', 1)->lockForUpdate()->first();
            if ($row === null) {
                return $this->fallbackDocnum();
            }

            $current = trim((string) ($row->billing_docnum ?? ''));
            if ($current === '') {
                $current = '00000001';
            }

            $next = $this->nextSeries($current);
            DB::table('syspar')->where('recid', 1)->update(['billing_docnum' => $next]);

            return $next;
        });
    }

    /**
     * @return list<array{dstcde: string, dstdsc: string}>
     */
    public function destinations(): array
    {
        if (! Schema::hasTable('destinationfile')) {
            return [];
        }

        return DB::table('destinationfile')
            ->orderBy('dstdsc')
            ->get(['dstcde', 'dstdsc'])
            ->map(fn ($row) => [
                'dstcde' => trim((string) ($row->dstcde ?? '')),
                'dstdsc' => trim((string) ($row->dstdsc ?? '')),
            ])
            ->filter(fn ($row) => $row['dstcde'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{cuscde: string, cusdsc: string, telno: string, email: string}
     */
    public function shipperInfo(string $cuscde): array
    {
        $info = [
            'cuscde' => $cuscde,
            'cusdsc' => $cuscde,
            'telno' => '',
            'email' => '',
        ];
        if (! Schema::hasTable('customerfile') || $cuscde === '') {
            return $info;
        }

        $columns = ['cuscde', 'cusdsc', 'telno'];
        if (Schema::hasColumn('customerfile', 'email')) {
            $columns[] = 'email';
        }

        $row = DB::table('customerfile')->where('cuscde', $cuscde)->first($columns);
        if ($row !== null) {
            $info['cusdsc'] = trim((string) ($row->cusdsc ?? $cuscde));
            $info['telno'] = trim((string) ($row->telno ?? ''));
            $info['email'] = trim((string) ($row->email ?? ''));
        }

        $last = DB::table('billingtranfile1')
            ->where('cuscde', $cuscde)
            ->orderByDesc('recid')
            ->first(['cus_telno', 'cus_email']);
        if ($last !== null) {
            $tel = trim((string) ($last->cus_telno ?? ''));
            $email = trim((string) ($last->cus_email ?? ''));
            if ($tel !== '') {
                $info['telno'] = $tel;
            }
            if ($email !== '') {
                $info['email'] = $email;
            }
        }

        return $info;
    }

    /**
     * @return list<array{concde: string, telnum: string, email: string}>
     */
    public function searchConsignees(string $query): array
    {
        if (! Schema::hasTable('consigneefile')) {
            return [];
        }

        $columns = ['concde', 'telnum'];
        if (Schema::hasColumn('consigneefile', 'email')) {
            $columns[] = 'email';
        }

        $builder = DB::table('consigneefile')->orderBy('concde');
        if ($query !== '') {
            $builder->where('concde', 'like', $query.'%');
        }

        return $builder
            ->limit(100)
            ->get($columns)
            ->map(fn ($row) => [
                'concde' => trim((string) ($row->concde ?? '')),
                'telnum' => trim((string) ($row->telnum ?? '')),
                'email' => trim((string) ($row->email ?? '')),
            ])
            ->filter(fn ($row) => $row['concde'] !== '')
            ->values()
            ->all();
    }

    /**
     * Portal EIR picker: this shipper, unused billing_no only (no accpt_dte filter).
     *
     * @return list<array{docnum: string, vannum: string, concde: string, cuscde: string, dstcde: string, origin: string}>
     */
    public function eirCandidates(string $cuscde, string $search = ''): array
    {
        if (! Schema::hasTable('eirtranfile1') || $cuscde === '') {
            return [];
        }

        $builder = DB::table('eirtranfile1')
            ->where('cuscde', $cuscde)
            ->where(function ($inner) {
                $inner->whereNull('billing_no')->orWhere('billing_no', '');
            })
            ->orderBy('recid');

        if ($search !== '') {
            $builder->where('docnum', 'like', '%'.$search.'%');
        }

        return $builder
            ->get(['docnum', 'vannum', 'concde', 'cuscde', 'dstcde', 'origin'])
            ->map(fn ($row) => [
                'docnum' => trim((string) ($row->docnum ?? '')),
                'vannum' => trim((string) ($row->vannum ?? '')),
                'concde' => trim((string) ($row->concde ?? '')),
                'cuscde' => trim((string) ($row->cuscde ?? '')),
                'dstcde' => trim((string) ($row->dstcde ?? '')),
                'origin' => trim((string) ($row->origin ?? '')),
            ])
            ->filter(fn ($row) => $row['docnum'] !== '')
            ->values()
            ->all();
    }

    public function eirByDocnum(string $eirNo): ?object
    {
        if (! Schema::hasTable('eirtranfile1') || $eirNo === '') {
            return null;
        }

        return DB::table('eirtranfile1')->where('docnum', $eirNo)->first(['docnum', 'vannum', 'cuscde', 'concde']);
    }

    public function eirUsedOnOtherBilling(string $eirNo, string $currentDocnum): bool
    {
        if ($eirNo === '') {
            return false;
        }

        $query = BillingForm::query()->where('eir_no', $eirNo);
        if ($currentDocnum !== '') {
            $query->where('docnum', '<>', $currentDocnum);
        }

        return $query->exists();
    }

    /**
     * @return array{measurement: string, success: bool, error?: string}
     */
    public function vanMeasurement(string $vannum): array
    {
        $category = $this->categoryForVan($vannum);
        if ($category['error'] !== null) {
            return ['measurement' => '', 'success' => false, 'error' => $category['error']];
        }

        $measurement = trim((string) ($category['row']->untmea ?? ''));

        return ['measurement' => $measurement, 'success' => true];
    }

    /**
     * @return array{success: bool, decvalmax?: float, error?: string}
     */
    public function declaredValueLimit(string $vannum, float $value): array
    {
        $category = $this->categoryForVan($vannum);
        if ($category['error'] !== null) {
            return ['success' => false, 'error' => $category['error']];
        }

        $max = (float) ($category['row']->decvalmax ?? 0);
        if ($value > $max) {
            return [
                'success' => false,
                'error' => 'The amount you entered has reached the maximum value ('.$max.').',
            ];
        }

        return ['success' => true, 'decvalmax' => $max];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function weightLimit(string $vannum, float $weight, string $dstcde): array
    {
        $category = $this->categoryForVan($vannum);
        if ($category['error'] !== null) {
            return ['success' => false, 'error' => $category['error']];
        }

        if (! Schema::hasTable('weightlimitfile')) {
            return ['success' => true];
        }

        $catcde = trim((string) ($category['row']->catcde ?? ''));
        $row = DB::table('weightlimitfile')
            ->where('dstcde', $dstcde)
            ->where('catcde', $catcde)
            ->first(['weight']);
        if ($row === null) {
            return ['success' => true];
        }

        $limit = (float) ($row->weight ?? 0);
        if ($weight > $limit) {
            return [
                'success' => false,
                'error' => 'The weight you entered has exceeded the maximum weight limit ('.$limit.').',
            ];
        }

        return ['success' => true];
    }

    public function eirExists(string $eirNo): bool
    {
        return $this->eirByDocnum($eirNo) !== null;
    }

    public function relinkEir(?string $originalEir, string $newEir, string $billingDocnum): void
    {
        if (! Schema::hasTable('eirtranfile1')) {
            return;
        }

        $original = trim((string) $originalEir);
        if ($original !== '') {
            DB::table('eirtranfile1')
                ->where('docnum', $original)
                ->where('billing_no', $billingDocnum)
                ->update(['billing_no' => '']);
        }

        if ($newEir !== '' && $this->eirExists($newEir)) {
            DB::table('eirtranfile1')->where('docnum', $newEir)->update(['billing_no' => $billingDocnum]);
        }
    }

    public function clearEirBilling(string $billingDocnum): void
    {
        if (! Schema::hasTable('eirtranfile1')) {
            return;
        }

        DB::table('eirtranfile1')->where('billing_no', $billingDocnum)->update(['billing_no' => '']);
    }

    /**
     * @return array{name: string, add1: string, tin: string, telno: string, faxnum: string, email: string}
     */
    public function company(): array
    {
        $empty = [
            'name' => '',
            'add1' => '',
            'tin' => '',
            'telno' => '',
            'faxnum' => '',
            'email' => '',
        ];
        if (! Schema::hasTable('companyfile')) {
            return $empty;
        }

        $row = DB::table('companyfile')->first();
        if ($row === null) {
            return $empty;
        }

        $name = trim((string) ($row->comdsc ?? ''));
        if ($name === '') {
            $name = trim((string) ($row->companydescription ?? ''));
        }

        return [
            'name' => $name,
            'add1' => trim((string) ($row->companyadd1 ?? '')),
            'tin' => trim((string) ($row->companytin ?? '')),
            'telno' => trim((string) ($row->telno ?? '')),
            'faxnum' => trim((string) ($row->faxnum ?? '')),
            'email' => trim((string) ($row->email ?? '')),
        ];
    }

    /**
     * @return array{cusdsc: string, condsc: string, dstdsc: string}
     */
    public function printNames(BillingForm $billing): array
    {
        $cusdsc = trim((string) ($billing->cuscde ?? ''));
        $condsc = trim((string) ($billing->concde ?? ''));
        $dstdsc = trim((string) ($billing->dstcde ?? ''));

        if (Schema::hasTable('customerfile') && $cusdsc !== '') {
            $name = trim((string) (DB::table('customerfile')->where('cuscde', $cusdsc)->value('cusdsc') ?? ''));
            if ($name !== '') {
                $cusdsc = $name;
            }
        }
        if (Schema::hasTable('consigneefile') && $condsc !== '') {
            $name = trim((string) (DB::table('consigneefile')->where('concde', $condsc)->value('condsc') ?? ''));
            if ($name !== '') {
                $condsc = $name;
            }
        }
        if (Schema::hasTable('destinationfile') && $dstdsc !== '') {
            $name = trim((string) (DB::table('destinationfile')->where('dstcde', $dstdsc)->value('dstdsc') ?? ''));
            if ($name !== '') {
                $dstdsc = $name;
            }
        }

        return [
            'cusdsc' => $cusdsc,
            'condsc' => $condsc,
            'dstdsc' => $dstdsc,
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<BillingForm>
     */
    private function filteredQuery(string $cuscde, string $search, string $sort = '', string $dir = 'desc')
    {
        [$column, $direction] = CrudList::order(
            $sort,
            $dir,
            ['docnum', 'eir_no'],
            'docnum',
            'desc',
        );

        $query = BillingForm::query()
            ->api()
            ->where('cuscde', $cuscde)
            ->where('approvalstatus', '!=', 'APPROVED')
            ->orderBy($column, $direction);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('docnum', 'like', $like)
                    ->orWhere('eir_no', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * @return array{row: object|null, error: string|null}
     */
    private function categoryForVan(string $vannum): array
    {
        if ($vannum === '') {
            return ['row' => null, 'error' => 'Van number is required'];
        }
        if (! Schema::hasTable('vanfile')) {
            return ['row' => null, 'error' => 'Van number not found'];
        }

        $van = DB::table('vanfile')->where('prevannum', $vannum)->first(['vandsc']);
        if ($van === null) {
            return ['row' => null, 'error' => 'Van number not found'];
        }

        $catcde = trim((string) ($van->vandsc ?? ''));
        if (! Schema::hasTable('categoryfile') || $catcde === '') {
            return ['row' => null, 'error' => 'Category not found for van number'];
        }

        $category = DB::table('categoryfile')->where('catcde', $catcde)->first(['catcde', 'untmea', 'decvalmax']);
        if ($category === null) {
            return ['row' => null, 'error' => 'Category not found for van number'];
        }

        return ['row' => $category, 'error' => null];
    }

    private function fallbackDocnum(): string
    {
        $last = BillingForm::query()->orderByDesc('docnum')->value('docnum');
        $current = trim((string) ($last ?? ''));
        if ($current === '') {
            return '00000001';
        }

        return $this->nextSeries($current);
    }

    /**
     * webmoreta stdfunc01.php LNexts: increment from the right.
     */
    private function nextSeries(string $value): string
    {
        $carry = true;
        $result = '';
        for ($index = strlen($value) - 1; $index >= 0; $index--) {
            $char = $value[$index];
            if (! $carry) {
                return substr($value, 0, $index + 1).$result;
            }

            $code = ord($char);
            if ($code >= 48 && $code <= 57) {
                $carry = $char === '9';
                $char = substr((string) (((int) $char) + 1), -1);
            } elseif ($code >= 65 && $code <= 90) {
                $char = $code === 90 ? 'a' : chr($code + 1);
                $carry = false;
            } elseif ($code >= 97 && $code <= 122) {
                if ($code === 122) {
                    $char = 'A';
                    $carry = true;
                } else {
                    $char = chr($code + 1);
                    $carry = false;
                }
            }

            $result = $char.$result;
        }

        return $result;
    }
}
