<?php

namespace App\Services;

use App\Repositories\VanShipmentSummaryRepository;
use App\Support\VanShipmentSummaryPdf;
use Illuminate\Http\Response;

class VanShipmentSummaryService
{
    public function __construct(private VanShipmentSummaryRepository $summary) {}

    /**
     * @return array{destinations: list<array{dstcde: string, dstdsc: string}>}
     */
    public function lookups(): array
    {
        return [
            'destinations' => $this->summary->destinations(),
        ];
    }

    public function pdf(
        string $payee,
        string $cuscde,
        string $concde,
        string $origin,
        string $dstcde,
        string $from,
        string $to,
    ): Response {
        $payee = $payee === 'consignee' ? 'consignee' : 'shipper';
        $cuscde = trim($cuscde);
        $concde = trim($concde);
        $origin = trim($origin);
        $dstcde = trim($dstcde);
        $fromSql = $this->sqlDate($from);
        $toSql = $this->sqlDate($to);
        $all = $cuscde === '' && $concde === '';

        $categories = $this->summary->headerCategories();
        $catcdes = array_map(static fn ($row) => $row['catcde'], $categories);

        $shippers = [];
        $consignees = [];
        $grandCounts = [];
        $grandTotal = 0;

        if ($all || $payee === 'shipper') {
            $shippers = $this->partyBlock(
                'shipper',
                $all ? '' : $cuscde,
                $origin,
                $dstcde,
                $fromSql,
                $toSql,
                $categories,
                $catcdes,
                $grandCounts,
                $grandTotal,
            );
        }
        if ($all || $payee === 'consignee') {
            $consignees = $this->partyBlock(
                'consignee',
                $all ? '' : $concde,
                $origin,
                $dstcde,
                $fromSql,
                $toSql,
                $categories,
                $catcdes,
                $grandCounts,
                $grandTotal,
            );
        }

        return new Response(
            VanShipmentSummaryPdf::make()->render(
                $this->summary->companyName(),
                $origin,
                $dstcde,
                $fromSql.' To '.$toSql,
                now('Asia/Manila')->format('m/d/Y'),
                $categories,
                $shippers,
                $consignees,
                $grandCounts,
                $grandTotal,
            ),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="van-shipment-summary.pdf"',
            ],
        );
    }

    /**
     * @param  list<array{tag: string, catcde: string}>  $categories
     * @param  list<string>  $catcdes
     * @param  array<string, int>  $grandCounts
     * @return list<array{name: string, rows: list<array{voynum: string, docnum: string, counts: array<string, int>}>, subtotals: array<string, int>, line_total: int}>
     */
    private function partyBlock(
        string $payee,
        string $code,
        string $origin,
        string $dstcde,
        string $from,
        string $to,
        array $categories,
        array $catcdes,
        array &$grandCounts,
        int &$grandTotal,
    ): array {
        $codes = $this->summary->partyCodes($payee, $code, $origin, $dstcde, $from, $to);
        $voyagesByCode = [];
        $allVoynums = [];
        foreach ($codes as $partyCode) {
            $voyages = $this->summary->voyages($payee, $partyCode, $origin, $dstcde, $from, $to);
            $voyagesByCode[$partyCode] = $voyages;
            foreach ($voyages as $voyage) {
                $allVoynums[] = trim((string) ($voyage->voynum ?? ''));
            }
        }

        $counts = $this->summary->categoryCounts($payee, $codes, $allVoynums, $catcdes);
        $parties = [];
        foreach ($codes as $partyCode) {
            $rows = [];
            $subtotals = [];
            $lineTotal = 0;
            foreach ($voyagesByCode[$partyCode] as $voyage) {
                $voynum = trim((string) ($voyage->voynum ?? ''));
                $rowCounts = [];
                $rowTotal = 0;
                foreach ($categories as $category) {
                    $key = $voynum.'|'.$partyCode.'|'.$category['catcde'];
                    $count = $counts[$key] ?? 0;
                    $rowCounts[$category['tag']] = $count;
                    $rowTotal += $count;
                    $subtotals[$category['tag']] = ($subtotals[$category['tag']] ?? 0) + $count;
                    $grandCounts[$category['tag']] = ($grandCounts[$category['tag']] ?? 0) + $count;
                }
                $lineTotal += $rowTotal;
                $grandTotal += $rowTotal;
                $rows[] = [
                    'voynum' => $voynum,
                    'docnum' => trim((string) ($voyage->docnum ?? '')),
                    'counts' => $rowCounts,
                ];
            }
            $parties[] = [
                'name' => $payee === 'consignee'
                    ? $this->summary->consigneeName($partyCode)
                    : $this->summary->shipperName($partyCode),
                'rows' => $rows,
                'subtotals' => $subtotals,
                'line_total' => $lineTotal,
            ];
        }

        return $parties;
    }

    private function sqlDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
            return substr($value, 0, 10);
        }
        $stamp = strtotime($value);

        return $stamp === false ? '' : date('Y-m-d', $stamp);
    }
}
