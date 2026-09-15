<?php

namespace App\Http\Controllers;

use App\Http\Requests\VanShipmentSummaryPdfRequest;
use App\Services\VanShipmentSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class VanShipmentSummaryController extends Controller
{
    public function __construct(private VanShipmentSummaryService $summary) {}

    public function lookups(): JsonResponse
    {
        return response()->json($this->summary->lookups());
    }

    public function pdf(VanShipmentSummaryPdfRequest $request): Response
    {
        $validated = $request->validated();

        return $this->summary->pdf(
            trim((string) ($validated['payee'] ?? 'shipper')),
            trim((string) ($validated['cuscde'] ?? '')),
            trim((string) ($validated['concde'] ?? '')),
            trim((string) ($validated['origin'] ?? '')),
            trim((string) ($validated['dstcde'] ?? '')),
            trim((string) ($validated['date_from'] ?? '')),
            trim((string) ($validated['date_to'] ?? '')),
        );
    }
}
