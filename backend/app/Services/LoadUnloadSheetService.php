<?php

namespace App\Services;

use App\Repositories\LoadUnloadSheetRepository;
use App\Support\LoadUnloadSheetPdf;
use Illuminate\Http\Response;

class LoadUnloadSheetService
{
    public function __construct(private LoadUnloadSheetRepository $sheet) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function pdf(array $filters): Response
    {
        $voynum = trim((string) ($filters['txtvoynum'] ?? $filters['voynum'] ?? ''));
        if ($voynum === '') {
            return LoadUnloadSheetPdf::errorResponse('Please Select Voyage Number.');
        }

        $sort = trim((string) ($filters['txtsort'] ?? ''));
        $voyage = $this->sheet->voyage($voynum);
        $rows = [];
        foreach ($this->sheet->lines($voynum, $sort) as $line) {
            $description = trim((string) ($line->classdsc ?? ''));
            if ($description === '') {
                continue;
            }
            $qty = $line->qty;
            $qtyText = ($qty === null || (float) $qty == 0.0)
                ? ''
                : trim((string) $qty.' '.trim((string) ($line->class ?? '')));
            $itmqty = $line->itmqty;
            $mea = ($itmqty === null || (float) $itmqty == 0.0) ? '' : number_format((float) $itmqty, 2);
            $soc = strtoupper(trim((string) ($line->soc ?? ''))) === 'Y';
            $rows[] = [
                'bolnum' => trim((string) ($line->docnum ?? '')),
                'marks' => substr(trim((string) ($line->itmcde ?? '')), 0, 10),
                'qty' => substr($qtyText, 0, 10),
                'catcde' => substr(trim((string) ($line->catcde ?? '')), 0, 24),
                'vannum' => substr($soc ? trim((string) ($line->socvannum ?? '')) : trim((string) ($line->vannum ?? '')), 0, 15),
                'sealnum' => substr(trim((string) ($line->sealnum ?? '')), 0, 10),
                'description' => substr($description, 0, 20),
                'mos' => substr(trim((string) ($line->shipmode ?? '')), 0, 15),
                'consignee' => substr(trim((string) ($line->concde ?? '')), 0, 18),
                'shipper' => substr(trim((string) ($line->cuscde ?? '')), 0, 18),
                'mea' => $mea,
            ];
        }

        return new Response(
            LoadUnloadSheetPdf::make()->render([
                'company' => $this->sheet->companyName(),
                'voynum' => $voynum,
                'sailing_date' => $this->displayDate((string) ($voyage['trndte'] ?? '')),
                'origin' => (string) ($voyage['origin'] ?? ''),
                'destination' => (string) ($voyage['dstcde'] ?? ''),
                'printed_at' => now('Asia/Manila')->format('F j, Y h:i:s A'),
                'rows' => $rows,
            ]),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="load-unload-sheet.pdf"',
            ],
        );
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
