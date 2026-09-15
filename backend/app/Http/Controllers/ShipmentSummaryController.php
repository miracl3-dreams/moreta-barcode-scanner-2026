<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShipmentSummaryPdfRequest;
use App\Services\ShipmentSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShipmentSummaryController extends Controller
{
    public function __construct(private ShipmentSummaryService $summary) {}

    public function lookups(): JsonResponse
    {
        return response()->json($this->summary->lookups());
    }

    public function searchShippers(Request $request): JsonResponse
    {
        return response()->json([
            'items' => $this->summary->searchShippers(trim((string) $request->query('q', ''))),
        ]);
    }

    public function searchConsignees(Request $request): JsonResponse
    {
        return response()->json([
            'items' => $this->summary->searchConsignees(trim((string) $request->query('q', ''))),
        ]);
    }

    public function searchVoyages(Request $request): JsonResponse
    {
        return response()->json([
            'items' => $this->summary->searchVoyages(trim((string) $request->query('q', ''))),
        ]);
    }

    public function pdf(ShipmentSummaryPdfRequest $request): Response
    {
        $validated = $request->validated();

        return $this->summary->pdf(
            trim((string) ($validated['filter'] ?? 'bydst')),
            trim((string) ($validated['payee'] ?? 'shipper')),
            trim((string) ($validated['cuscde'] ?? '')),
            trim((string) ($validated['concde'] ?? '')),
            trim((string) ($validated['dstcde'] ?? '')),
            trim((string) ($validated['voynum'] ?? '')),
            trim((string) ($validated['date_from'] ?? '')),
            trim((string) ($validated['date_to'] ?? '')),
        );
    }
}
