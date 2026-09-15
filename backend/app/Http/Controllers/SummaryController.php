<?php

namespace App\Http\Controllers;

use App\Http\Requests\SummaryPdfRequest;
use App\Services\SummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SummaryController extends Controller
{
    public function __construct(private SummaryService $summary) {}

    public function lookups(): JsonResponse
    {
        return response()->json($this->summary->lookups());
    }

    public function paymentList(SummaryPdfRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json($this->summary->paymentList(
            trim((string) ($validated['voynum'] ?? '')),
            trim((string) ($validated['paytrmcde'] ?? '')),
            trim((string) ($validated['cusdsc'] ?? '')),
            trim((string) ($validated['condsc'] ?? '')),
            $validated['per_page'] ?? 10,
        ));
    }

    public function paymentPdf(SummaryPdfRequest $request): Response
    {
        $validated = $request->validated();

        return $this->summary->paymentPdf(
            trim((string) ($validated['voynum'] ?? '')),
            trim((string) ($validated['paytrmcde'] ?? '')),
            trim((string) ($validated['cusdsc'] ?? '')),
            trim((string) ($validated['condsc'] ?? '')),
        );
    }

    public function cargoPdf(SummaryPdfRequest $request): Response
    {
        $validated = $request->validated();

        return $this->summary->cargoPdf(
            trim((string) ($validated['voynum'] ?? '')),
            trim((string) ($validated['catcde'] ?? '')),
            trim((string) ($validated['shipmode'] ?? '')),
        );
    }
}
