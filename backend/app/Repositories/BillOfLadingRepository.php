<?php

namespace App\Repositories;

use App\Models\BillOfLading;
use App\Models\BillOfLadingLine;
use App\Models\User;
use App\Support\Amount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BillOfLadingRepository
{
    /**
     * @return Collection<int, BillOfLading>
     */
    public function listByVoynum(string $voynum): Collection
    {
        return BillOfLading::query()
            ->api()
            ->where('voynum', $voynum)
            ->orderBy('docnum')
            ->get();
    }

    public function findDetail(int $recid): ?BillOfLading
    {
        return BillOfLading::query()->detail()->where('recid', $recid)->first();
    }

    public function findDetailByVoynumDocnum(string $voynum, string $docnum): ?BillOfLading
    {
        return BillOfLading::query()
            ->detail()
            ->where('voynum', $voynum)
            ->where('docnum', $docnum)
            ->first();
    }

    public function findDetailOnVoyage(string $voynum, int $recid): ?BillOfLading
    {
        return BillOfLading::query()
            ->detail()
            ->where('voynum', $voynum)
            ->where('recid', $recid)
            ->lockForUpdate()
            ->first();
    }

    public function existsDocnum(string $docnum, ?int $exceptRecid = null): bool
    {
        $query = BillOfLading::query()->where('docnum', $docnum);
        if ($exceptRecid !== null) {
            $query->where('recid', '!=', $exceptRecid);
        }

        return $query->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createHeader(array $attributes): BillOfLading
    {
        return BillOfLading::query()->create($attributes);
    }

    /**
     * @return Collection<int, BillOfLadingLine>
     */
    public function listCargoLines(string $voynum, string $docnum): Collection
    {
        return BillOfLadingLine::query()
            ->select(BillOfLadingLine::CHARGE_LINE_COLUMNS)
            ->where('voynum', $voynum)
            ->where('docnum', $docnum)
            ->where('trncde', 'BL')
            ->orderBy('linenum')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createLine(array $attributes): BillOfLadingLine
    {
        return BillOfLadingLine::query()->create($attributes);
    }

    public function deleteHeader(int $recid): void
    {
        BillOfLading::query()->where('recid', $recid)->delete();
    }

    public function deleteHeaderByDocnum(string $docnum): void
    {
        BillOfLading::query()->where('docnum', $docnum)->delete();
    }

    public function deleteLines(string $voynum, string $docnum): void
    {
        BillOfLadingLine::query()->where('voynum', $voynum)->where('docnum', $docnum)->delete();
        if (Schema::hasTable('billofladingfile3')) {
            DB::table('billofladingfile3')->where('voynum', $voynum)->where('docnum', $docnum)->delete();
        }
    }

    public function shipperExists(string $cuscde): bool
    {
        return DB::table('customerfile')->where('cuscde', $cuscde)->exists();
    }

    public function shipperMatches(string $cuscde, string $cusdsc): bool
    {
        return DB::table('customerfile')->where('cuscde', $cuscde)->where('cusdsc', $cusdsc)->exists();
    }

    public function consigneeExists(string $concde): bool
    {
        return DB::table('consigneefile')->where('concde', $concde)->exists();
    }

    public function consigneeMatches(string $concde, string $condsc): bool
    {
        return DB::table('consigneefile')->where('concde', $concde)->where('condsc', $condsc)->exists();
    }

    /**
     * @return list<array{chargefld: string, chargedesc: string, editable: bool}>
     */
    public function shownCharges(): array
    {
        $rows = DB::table('chargesfile')
            ->where('is_show', 1)
            ->orderBy('sortorder')
            ->get(['chargefld', 'chargedesc', 'is_editable']);

        return $this->mapChargeRows($rows);
    }

    public function creditTermExists(string $trmcde, string $trmdsc): bool
    {
        return DB::table('termfile')->where('trmcde', $trmcde)->where('trmdsc', $trmdsc)->exists();
    }

    public function termDays(string $trmcde): ?int
    {
        $row = DB::table('termfile')->where('trmcde', $trmcde)->first(['trmday']);
        if ($row === null || $row->trmday === null || $row->trmday === '') {
            return null;
        }

        return (int) $row->trmday;
    }

    public function voyageIsApproved(string $voynum): bool
    {
        if (! Schema::hasTable('billingtranfile1')) {
            return false;
        }

        $status = DB::table('billingtranfile1')
            ->where('voynum_tag', $voynum)
            ->value('approvalstatus');

        return strtoupper(trim((string) ($status ?? ''))) === 'APPROVED';
    }

    public function clearBillingTag(?string $billingNo): void
    {
        $billingNo = trim((string) $billingNo);
        if ($billingNo === '' || ! Schema::hasTable('billingtranfile1')) {
            return;
        }

        DB::table('billingtranfile1')
            ->where('docnum', $billingNo)
            ->update([
                'approvalstatus' => 'FOR APPROVAL',
                'voynum_tag' => '',
            ]);
    }

    /**
     * @return list<array{cuscde: string, cusdsc: string, telno: string}>
     */
    public function shipperOptions(): array
    {
        return DB::table('customerfile')
            ->orderBy('cusdsc')
            ->get(['cuscde', 'cusdsc', 'telno'])
            ->map(fn ($row) => [
                'cuscde' => trim((string) ($row->cuscde ?? '')),
                'cusdsc' => trim((string) ($row->cusdsc ?? '')),
                'telno' => trim((string) ($row->telno ?? '')),
            ])
            ->filter(fn ($row) => $row['cuscde'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array{concde: string, condsc: string, telnum: string}>
     */
    public function consigneeOptions(): array
    {
        return DB::table('consigneefile')
            ->orderBy('condsc')
            ->get(['concde', 'condsc', 'telnum'])
            ->map(fn ($row) => [
                'concde' => trim((string) ($row->concde ?? '')),
                'condsc' => trim((string) ($row->condsc ?? '')),
                'telnum' => trim((string) ($row->telnum ?? '')),
            ])
            ->filter(fn ($row) => $row['concde'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array{paytrmcde: string, paytrmdsc: string}>
     */
    public function paymentTermOptions(): array
    {
        return DB::table('paymenttermfile')
            ->orderBy('paytrmcde')
            ->get(['paytrmcde', 'paytrmdsc'])
            ->map(fn ($row) => [
                'paytrmcde' => trim((string) ($row->paytrmcde ?? '')),
                'paytrmdsc' => trim((string) ($row->paytrmdsc ?? '')),
            ])
            ->filter(fn ($row) => $row['paytrmcde'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function shipmentModeOptions(): array
    {
        return DB::table('shipmentmodefile')
            ->orderBy('shipmode')
            ->pluck('shipmode')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{trmcde: string, trmdsc: string}>
     */
    public function creditTermOptions(): array
    {
        return DB::table('termfile')
            ->orderBy('trmdsc')
            ->get(['trmcde', 'trmdsc'])
            ->map(fn ($row) => [
                'trmcde' => trim((string) ($row->trmcde ?? '')),
                'trmdsc' => trim((string) ($row->trmdsc ?? '')),
            ])
            ->filter(fn ($row) => $row['trmcde'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function checkerOptions(): array
    {
        return DB::table('checkerfile')
            ->orderBy('cuscde')
            ->pluck('cuscde')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function unitOptions(): array
    {
        return DB::table('itemunitfile')
            ->orderBy('untmea')
            ->pluck('untmea')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{catcde: string, catdsc: string, iscontainer: string, untmea: string, meaamt: string}>
     */
    public function bolCategoryOptions(): array
    {
        return DB::table('categoryfile')
            ->where('inBL', 'Y')
            ->orderBy('catcde')
            ->get(['catcde', 'catdsc', 'iscontainer', 'untmea', 'meaamt'])
            ->map(fn ($row) => [
                'catcde' => trim((string) ($row->catcde ?? '')),
                'catdsc' => trim((string) ($row->catdsc ?? '')),
                'iscontainer' => trim((string) ($row->iscontainer ?? '')),
                'untmea' => trim((string) ($row->untmea ?? '')),
                'meaamt' => trim((string) ($row->meaamt ?? '')),
            ])
            ->filter(fn ($row) => $row['catcde'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function emptyVanCategoryOptions(): array
    {
        return DB::table('categoryfile')
            ->where('iscontainer', 'Y')
            ->orderBy('catcde')
            ->pluck('catcde')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function detailMax(): int
    {
        $value = (int) (DB::table('syspar')->value('detail_max') ?? 1);

        return $value > 0 ? min($value, 40) : 20;
    }

    /**
     * @return list<array{chargefld: string, chargedesc: string, editable: bool}>
     */
    public function chargeFields(User $user): array
    {
        $level = strtoupper(trim((string) ($user->usrlvl ?? '')));
        if ($level === 'USER' && Schema::hasTable('chargesfile2')) {
            $rows = DB::table('chargesfile2')
                ->where('usercde', (string) $user->recid)
                ->where('is_show', 1)
                ->orderBy('sortorder')
                ->get(['chargefld', 'chargedesc', 'is_editable']);
            if ($rows->isNotEmpty()) {
                return $this->mapChargeRows($rows);
            }
        }

        $rows = DB::table('chargesfile')
            ->where('is_show', 1)
            ->orderBy('sortorder')
            ->get(['chargefld', 'chargedesc', 'is_editable']);

        return $this->mapChargeRows($rows);
    }

    /**
     * @return list<string>
     */
    public function freeVans(string $origin, string $voytype, string $catcde): array
    {
        $query = DB::table('vanfile')->where(function ($inner) {
            $inner->where('isConverted', '!=', 1)->orWhereNull('isConverted');
        });

        if (strtoupper($voytype) === 'REG') {
            $query->where('lastloc', $origin)->where('isFree', 1);
        }

        $like = '%'.$catcde.'%';
        if ($catcde === 'LCL-in container' || $catcde === 'LCL-not in container') {
            $query->where(function ($inner) use ($like) {
                $inner->where('vandsc', 'like', $like)
                    ->orWhere('vandsc', 'like', '%Containerized 10%')
                    ->orWhere('vandsc', 'like', '%Containerized 20%');
            });
        } elseif ($catcde === 'Vehicles in Container') {
            $query->where(function ($inner) use ($like) {
                $inner->where('vandsc', 'like', $like)
                    ->orWhere('vandsc', 'like', '%Containerized 20%');
            });
        } elseif ($catcde !== '') {
            $query->where('vandsc', 'like', $like);
        }

        return $query
            ->orderBy('prevannum')
            ->get(['prefix', 'vannum', 'prevannum'])
            ->map(function ($row) {
                $prev = trim((string) ($row->prevannum ?? ''));
                if ($prev !== '') {
                    return $prev;
                }

                return trim((string) ($row->prefix ?? '')).trim((string) ($row->vannum ?? ''));
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Empty-van container list: vandsc LIKE stripped category, lastloc = origin, isFree.
     *
     * @return list<string>
     */
    public function emptyVanContainers(string $origin, string $catcde): array
    {
        $needle = $catcde;
        if (strlen($needle) > 4) {
            $needle = substr($needle, 0, strlen($needle) - 4);
        }

        return DB::table('vanfile')
            ->where('vandsc', 'like', '%'.$needle.'%')
            ->where('lastloc', $origin)
            ->where('isFree', 1)
            ->orderBy('prevannum')
            ->pluck('prevannum')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, BillOfLadingLine>
     */
    public function listEmptyVans(string $voynum): Collection
    {
        return BillOfLadingLine::query()
            ->select(BillOfLadingLine::EMPTY_VAN_COLUMNS)
            ->where('voynum', $voynum)
            ->where('trncde', 'EV')
            ->orderBy('recid')
            ->get();
    }

    public function findEmptyVan(string $voynum, int $recid): ?BillOfLadingLine
    {
        return BillOfLadingLine::query()
            ->select(BillOfLadingLine::EMPTY_VAN_COLUMNS)
            ->where('voynum', $voynum)
            ->where('trncde', 'EV')
            ->where('recid', $recid)
            ->first();
    }

    public function deleteEmptyVan(int $recid): void
    {
        BillOfLadingLine::query()->where('recid', $recid)->where('trncde', 'EV')->delete();
    }

    public function unlockSealsForDocument(string $voynum, string $docnum): void
    {
        $seals = DB::table('billofladingfile2')
            ->where('voynum', $voynum)
            ->where('docnum', $docnum)
            ->where('trncde', 'BL')
            ->whereNotNull('sealnum')
            ->where('sealnum', '!=', '')
            ->pluck('sealnum');

        foreach ($seals as $sealnum) {
            $seal = trim((string) $sealnum);
            if ($seal === '') {
                continue;
            }

            DB::table('sealfile')
                ->where('sealnum', $seal)
                ->update([
                    'islock' => 'N',
                    'docnum' => '',
                ]);
        }
    }

    public function updateVanLocation(string $prevannum, string $lastloc, ?string $isFree = null): void
    {
        if ($prevannum === '') {
            return;
        }

        $update = ['lastloc' => $lastloc];
        if ($isFree !== null) {
            $update['isFree'] = $isFree;
        }

        DB::table('vanfile')
            ->where('prevannum', $prevannum)
            ->update($update);
    }

    public function voyageExists(string $voynum): bool
    {
        return DB::table('voyagefile')->where('voynum', $voynum)->exists();
    }

    /**
     * @return list<string>
     */
    public function searchSeals(string $search): array
    {
        $query = DB::table('sealfile')
            ->where(function ($inner) {
                $inner->where('islock', '!=', 'Y')->orWhereNull('islock')->orWhere('islock', '');
            })
            ->orderBy('sealnum')
            ->limit(10);

        if ($search !== '') {
            $query->where('sealnum', 'like', $search.'%');
        }

        return $query
            ->pluck('sealnum')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
    }

    public function sealExists(string $sealnum): bool
    {
        return DB::table('sealfile')->where('sealnum', $sealnum)->exists();
    }

    public function createSeal(string $sealnum): void
    {
        DB::table('sealfile')->insert([
            'sealnum' => $sealnum,
            'remarks' => '',
            'islock' => 'N',
            'docnum' => '',
        ]);
    }

    public function lockSeal(string $sealnum): void
    {
        if ($sealnum === '') {
            return;
        }

        DB::table('sealfile')->where('sealnum', $sealnum)->update(['islock' => 'Y']);
    }

    public function unlockSeal(string $sealnum): void
    {
        if ($sealnum === '') {
            return;
        }

        DB::table('sealfile')->where('sealnum', $sealnum)->update([
            'islock' => 'N',
            'docnum' => '',
        ]);
    }

    /**
     * @return list<string>
     */
    public function documentSeals(string $voynum, string $docnum): array
    {
        return DB::table('billofladingfile2')
            ->where('voynum', $voynum)
            ->where('docnum', $docnum)
            ->where('trncde', 'BL')
            ->whereNotNull('sealnum')
            ->where('sealnum', '!=', '')
            ->pluck('sealnum')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array{recid: int, catcde: string, remarks: string, rate: string}>
     */
    public function ratesForPayee(string $payee, string $code): array
    {
        $table = strtolower($payee) === 'consignee' ? 'consigneeratesfile' : 'shipperratesfile';
        if (! Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)
            ->where('cuscde', $code)
            ->orderBy('catcde')
            ->orderBy('rate')
            ->get(['recid', 'catcde', 'remarks', 'rate'])
            ->map(fn ($row) => [
                'recid' => (int) $row->recid,
                'catcde' => trim((string) ($row->catcde ?? '')),
                'remarks' => trim((string) ($row->remarks ?? '')),
                'rate' => Amount::display($row->rate),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createCategoryRate(array $attributes): void
    {
        if (! Schema::hasTable('categoryratesfile')) {
            return;
        }

        DB::table('categoryratesfile')->insert($attributes);
    }

    public function vanPrefix(string $vannum): string
    {
        $row = DB::table('vanfile')
            ->where(function ($inner) use ($vannum) {
                $inner->where('prevannum', $vannum)->orWhere('vannum', $vannum);
            })
            ->first(['prefix']);

        return trim((string) ($row->prefix ?? ''));
    }

    public function categoryForVan(string $vannum): string
    {
        $vannum = trim($vannum);
        if ($vannum === '' || ! Schema::hasTable('vanfile')) {
            return '';
        }

        $vandsc = trim((string) (DB::table('vanfile')
            ->where(function ($inner) use ($vannum) {
                $inner->where('prevannum', $vannum)->orWhere('vannum', $vannum);
            })
            ->value('vandsc') ?? ''));

        if ($vandsc === '') {
            return '';
        }

        if (! Schema::hasTable('categoryfile')) {
            return $vandsc;
        }

        $best = '';
        foreach ($this->bolCategoryOptions() as $row) {
            $code = $row['catcde'];
            if (strcasecmp($code, $vandsc) === 0) {
                return $code;
            }
            if (
                (stripos($code, $vandsc) !== false || stripos($vandsc, $code) !== false)
                && strlen($code) > strlen($best)
            ) {
                $best = $code;
            }
        }

        return $best !== '' ? $best : $vandsc;
    }

    /**
     * @return list<array{docnum: string, trndte: string, amtapp: string}>
     */
    public function paymentsForDocapp(string $docapp): array
    {
        $docapp = trim($docapp);
        if ($docapp === '' || ! Schema::hasTable('arpaymentapplication')) {
            return [];
        }

        return DB::table('arpaymentapplication')
            ->where('docapp', $docapp)
            ->orderBy('trndte')
            ->orderBy('docnum')
            ->get(['docnum', 'trndte', 'amtapp'])
            ->map(fn ($row) => [
                'docnum' => trim((string) ($row->docnum ?? '')),
                'trndte' => substr(trim((string) ($row->trndte ?? '')), 0, 10),
                'amtapp' => Amount::format($row->amtapp),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return list<array{chargefld: string, chargedesc: string, editable: bool}>
     */
    private function mapChargeRows($rows): array
    {
        $allowed = array_flip(BillOfLading::CHARGE_FIELDS);

        return $rows
            ->map(function ($row) {
                $field = trim((string) ($row->chargefld ?? ''));
                $editable = strtolower(trim((string) ($row->is_editable ?? '')));

                return [
                    'chargefld' => $field,
                    'chargedesc' => trim((string) ($row->chargedesc ?? $field)),
                    'editable' => $editable === '' || $editable === '1' || ! str_contains($editable, 'readonly'),
                ];
            })
            ->filter(fn ($row) => $row['chargefld'] !== '' && isset($allowed[$row['chargefld']]))
            ->values()
            ->all();
    }
}
