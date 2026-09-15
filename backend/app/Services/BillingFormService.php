<?php

namespace App\Services;

use App\Models\BillingForm;
use App\Models\Customer;
use App\Repositories\BillingFormRepository;
use App\Support\Amount;
use App\Support\CrudList;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingFormService
{
    public function __construct(private BillingFormRepository $billings) {}

    /**
     * @return array{
     *     destinations: list<array{dstcde: string, dstdsc: string}>,
     *     shipper: array{cuscde: string, cusdsc: string, telno: string, email: string}
     * }
     */
    public function lookups(Customer $customer): array
    {
        $code = trim((string) $customer->cuscde);

        return [
            'destinations' => $this->billings->destinations(),
            'shipper' => $this->billings->shipperInfo($code),
        ];
    }

    /**
     * @return array{data: mixed, meta: array<string, int>}
     */
    public function list(Customer $customer, string $search, mixed $perPage, string $sort = '', string $dir = 'desc'): array
    {
        $cuscde = trim((string) $customer->cuscde);
        $pageSize = CrudList::perPage($perPage);
        if ($pageSize === 'all') {
            return CrudList::fromCollection($this->billings->getForList($cuscde, $search, $sort, $dir));
        }

        return CrudList::fromPaginator($this->billings->paginateForList($cuscde, $search, $pageSize, $sort, $dir));
    }

    public function owned(Customer $customer, int $recid): ?BillingForm
    {
        if ($recid < 1) {
            return null;
        }

        return $this->billings->findOwned($recid, trim((string) $customer->cuscde));
    }

    /**
     * @return array<string, mixed>
     */
    public function show(BillingForm $billing): array
    {
        return $this->detail($billing);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function create(array $validated, Customer $customer): array
    {
        $validated = $this->forceShipper($validated, $customer);

        return DB::transaction(function () use ($validated) {
            $this->assertSaveRules($validated, null);

            $docnum = Text::clip($this->billings->nextDocnum(), 20);
            if ($docnum === '') {
                throw ValidationException::withMessages([
                    'docnum' => ['Missing Control No.'],
                ]);
            }

            $billing = $this->billings->create($this->headerFromRequest($validated, $docnum, true));
            $this->billings->replaceLines($docnum, $this->linesFromRequest($validated, $docnum));
            $this->billings->relinkEir(null, Text::clip($validated['eir_no'] ?? '', 20), $docnum);

            return $this->detail($billing);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function update(BillingForm $billing, array $validated, Customer $customer): array
    {
        $validated = $this->forceShipper($validated, $customer);
        $docnum = trim((string) ($billing->docnum ?? ''));
        $originalEir = trim((string) ($validated['original_eir'] ?? $billing->eir_no ?? ''));

        return DB::transaction(function () use ($billing, $validated, $docnum, $originalEir) {
            $this->assertSaveRules($validated, $docnum);

            $billing = $this->billings->save($billing, $this->headerFromRequest($validated, $docnum, false));
            $this->billings->replaceLines($docnum, $this->linesFromRequest($validated, $docnum));
            $this->billings->relinkEir($originalEir, Text::clip($validated['eir_no'] ?? '', 20), $docnum);

            return $this->detail($billing);
        });
    }

    public function delete(BillingForm $billing): void
    {
        $this->billings->deleteByDocnum(trim((string) ($billing->docnum ?? '')));
    }

    /**
     * @return list<array{concde: string, telnum: string, email: string}>
     */
    public function searchConsignees(string $query): array
    {
        return $this->billings->searchConsignees($query);
    }

    /**
     * @return list<array{docnum: string, vannum: string, concde: string, cuscde: string, dstcde: string, origin: string}>
     */
    public function eirCandidates(Customer $customer, string $search): array
    {
        return $this->billings->eirCandidates(trim((string) $customer->cuscde), $search);
    }

    /**
     * @return array{measurement: string, success: bool, error?: string}
     */
    public function vanMeasurement(string $vannum): array
    {
        return $this->billings->vanMeasurement($vannum);
    }

    /**
     * @return array{success: bool, decvalmax?: float, error?: string}
     */
    public function declaredValueLimit(string $vannum, string $value): array
    {
        return $this->billings->declaredValueLimit($vannum, Amount::parse($value));
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function weightLimit(string $vannum, string $weight, string $dstcde): array
    {
        return $this->billings->weightLimit($vannum, Amount::parse($weight), $dstcde);
    }

    /**
     * @return array<string, mixed>
     */
    public function pdfPayload(BillingForm $billing): array
    {
        $names = $this->billings->printNames($billing);
        $company = $this->billings->company();
        $lines = $this->billings->lines(trim((string) $billing->docnum))
            ->map(fn ($line) => $line->toLineArray())
            ->all();

        return [
            'company' => $company,
            'docnum' => trim((string) ($billing->docnum ?? '')),
            'del_dte' => $this->displayDate($billing->del_dte),
            'cusdsc' => $names['cusdsc'],
            'condsc' => $names['condsc'],
            'dstdsc' => $names['dstdsc'],
            'cus_telno' => trim((string) ($billing->cus_telno ?? '')),
            'con_telno' => trim((string) ($billing->con_telno ?? '')),
            'cus_email' => trim((string) ($billing->cus_email ?? '')),
            'con_email' => trim((string) ($billing->con_email ?? '')),
            'vannum' => trim((string) ($billing->vannum ?? '')),
            'decleared_by' => strtoupper(trim((string) ($billing->decleared_by ?? ''))),
            'checked_by' => strtoupper(trim((string) ($billing->checked_by ?? ''))),
            'printed_at' => now('Asia/Manila')->format('F d, Y'),
            'lines' => $lines,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function forceShipper(array $validated, Customer $customer): array
    {
        $validated['cuscde'] = Text::clip($customer->cuscde ?? '', 100);

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertSaveRules(array $validated, ?string $currentDocnum): void
    {
        $eirNo = Text::clip($validated['eir_no'] ?? '', 20);
        $vannum = Text::clip($validated['vannum'] ?? '', 20);
        $eir = $this->billings->eirByDocnum($eirNo);
        if ($eir === null) {
            throw ValidationException::withMessages([
                'eir_no' => ['Please check the EIR Number. EIR number was not existing'],
            ]);
        }

        $eirVan = trim((string) ($eir->vannum ?? ''));
        if ($eirVan !== $vannum) {
            throw ValidationException::withMessages([
                'vannum' => ['Van Number mismatch. EIR No. '.$eirNo.' Van No. is '.$eirVan.', but you entered '.$vannum.'.'],
            ]);
        }

        if ($this->billings->eirUsedOnOtherBilling($eirNo, $currentDocnum ?? '')) {
            throw ValidationException::withMessages([
                'eir_no' => ['Please check the EIR Number. EIR number was already been used'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function headerFromRequest(array $validated, string $docnum, bool $creating): array
    {
        $header = [
            'docnum' => $docnum,
            'trndte' => $this->datePart($validated['trndte'] ?? ''),
            'del_dte' => $this->datePart($validated['del_dte'] ?? ''),
            'dstcde' => Text::clip($validated['dstcde'] ?? '', 50),
            'cuscde' => Text::clip($validated['cuscde'] ?? '', 100),
            'cus_telno' => Text::clip($validated['cus_telno'] ?? '', 50),
            'cus_email' => Text::clip($validated['cus_email'] ?? '', 100),
            'concde' => Text::clip($validated['concde'] ?? '', 50),
            'con_telno' => Text::clip($validated['con_telno'] ?? '', 50),
            'con_email' => Text::clip($validated['con_email'] ?? '', 100),
            'eir_no' => Text::clip($validated['eir_no'] ?? '', 20),
            'decleared_by' => Text::clip($validated['decleared_by'] ?? '', 100),
            'checked_by' => Text::clip($validated['checked_by'] ?? '', 100),
            'vannum' => Text::clip($validated['vannum'] ?? '', 20),
        ];
        if ($creating) {
            $header['approvalstatus'] = 'FOR APPROVAL';
        }

        return $header;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<array<string, mixed>>
     */
    private function linesFromRequest(array $validated, string $docnum): array
    {
        $rows = [];
        foreach ($validated['lines'] ?? [] as $line) {
            if (! is_array($line)) {
                continue;
            }
            $itmdesc = Text::clip($line['itmdesc'] ?? '', 100);
            $sealnum = Text::clip($line['sealnum'] ?? '', 50);
            $unit = Text::clip($line['unit'] ?? '', 20);
            $weight = Text::clip($line['weight'] ?? '', 20);
            $measurement = Text::clip($line['measurement'] ?? '', 50);
            $qtyText = str_replace(',', '', trim((string) ($line['qty'] ?? '')));
            $valueText = str_replace(',', '', trim((string) ($line['value'] ?? '')));
            if ($itmdesc === '' && $sealnum === '' && $unit === '' && $weight === '' && $measurement === '' && $qtyText === '' && $valueText === '') {
                continue;
            }

            $rows[] = [
                'docnum' => $docnum,
                'qty' => $qtyText === '' ? 0 : Amount::parse($qtyText),
                'unit' => $unit,
                'itmdesc' => $itmdesc,
                'sealnum' => $sealnum,
                'weight' => $weight,
                'measurement' => $measurement,
                'value' => $valueText === '' ? 0 : Amount::parse($valueText),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(BillingForm $billing): array
    {
        $lines = $this->billings->lines(trim((string) $billing->docnum))
            ->map(fn ($line) => $line->toLineArray())
            ->all();

        return $billing->toDetailArray($lines);
    }

    private function datePart(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return '';
        }

        return substr($text, 0, 10);
    }

    private function displayDate(mixed $value): string
    {
        $date = $this->datePart($value);
        if ($date === '') {
            return '';
        }

        $parsed = strtotime($date);

        return $parsed ? date('m-d-Y', $parsed) : $date;
    }
}
