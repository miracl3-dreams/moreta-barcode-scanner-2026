<?php

namespace App\Services;

use App\Repositories\VanEndorsementRepository;
use App\Support\VanEndorsementPdf;
use Illuminate\Http\Response;

class VanEndorsementService
{
    public function __construct(private VanEndorsementRepository $endorsement) {}

    public function pdf(int $recid): Response
    {
        return new Response(
            VanEndorsementPdf::make()->render($this->payload($recid)),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="van-endorsement.pdf"',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $recid): array
    {
        $company = $this->endorsement->company();
        $voyage = $this->endorsement->voyageByRecid($recid) ?? [
            'voynum' => '',
            'vsslcde' => '',
            'trndte' => '',
            'origin' => '',
            'dstdsc' => '',
        ];
        $categories = $this->endorsement->headerCategories();
        $voynum = $voyage['voynum'];

        $byCat = [];
        $max = 0;
        $vanNumbers = [];
        foreach ($categories as $category) {
            $catcde = $category['catcde'];
            $fullPrefix = substr($catcde, 0, 23);
            $full = $this->endorsement->cargoLines($voynum, 'BL', '%'.$fullPrefix.'%');
            $empty = $this->endorsement->cargoLines($voynum, 'EV', '%'.$catcde.'%');
            $fullRows = [];
            $descRows = [];
            foreach ($full as $line) {
                $van = trim((string) ($line->vannum ?? ''));
                $fullRows[] = $van;
                $descRows[] = trim((string) ($line->classdsc ?? ''));
                $vanNumbers[] = $van;
            }
            $mtRows = [];
            foreach ($empty as $line) {
                $van = trim((string) ($line->vannum ?? ''));
                $mtRows[] = $van;
                $vanNumbers[] = $van;
            }
            $byCat[$catcde] = [
                'full' => $fullRows,
                'mt' => $mtRows,
                'desc' => $descRows,
            ];
            $max = max($max, count($fullRows), count($mtRows));
        }

        $locations = $this->endorsement->vanLocations($vanNumbers);
        $rows = [];
        for ($i = 0; $i < $max; $i++) {
            $full = [];
            $mt = [];
            $fullLoc = [];
            $mtLoc = [];
            $descParts = [];
            foreach ($categories as $category) {
                $catcde = $category['catcde'];
                $fullVan = $byCat[$catcde]['full'][$i] ?? '';
                $mtVan = $byCat[$catcde]['mt'][$i] ?? '';
                $full[$catcde] = $fullVan;
                $mt[$catcde] = $mtVan;
                $fullLoc[$catcde] = $fullVan !== '' ? ($locations[$fullVan] ?? '') : '';
                $mtLoc[$catcde] = $mtVan !== '' ? ($locations[$mtVan] ?? '') : '';
                $part = $byCat[$catcde]['desc'][$i] ?? '';
                if ($part !== '') {
                    $descParts[] = $part;
                }
            }
            $rows[] = [
                'full' => $full,
                'mt' => $mt,
                'full_loc' => $fullLoc,
                'mt_loc' => $mtLoc,
                'desc' => implode(', ', $descParts),
            ];
        }

        return [
            'company' => $company['name'],
            'add1' => $company['add1'],
            'add2' => $company['add2'],
            'printed_at' => now('Asia/Manila')->format('F j, Y'),
            'voyage' => [
                'voynum' => $voyage['voynum'],
                'vsslcde' => $voyage['vsslcde'],
                'sailing' => $this->displayDate($voyage['trndte']),
                'origin' => $voyage['origin'],
                'dstdsc' => $voyage['dstdsc'],
            ],
            'categories' => $categories,
            'rows' => $rows,
        ];
    }

    private function displayDate(string $date): string
    {
        $date = trim($date);
        if ($date === '' || str_starts_with($date, '0000-00-00')) {
            return '';
        }
        $stamp = strtotime($date);

        return $stamp === false ? '' : date('n-j-Y', $stamp);
    }
}
