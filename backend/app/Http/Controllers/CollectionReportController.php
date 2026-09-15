<?php

namespace App\Http\Controllers;

use App\Http\Requests\CollectionReportPdfRequest;
use App\Services\CollectionReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CollectionReportController extends Controller
{
    public function __construct(private CollectionReportService $report) {}

    public function lookups(Request $request): JsonResponse
    {
        return response()->json($this->report->lookups($request->user()));
    }

    public function pdf(CollectionReportPdfRequest $request): Response
    {
        return $this->report->pdf($request->validated(), $request->user());
    }

    public function export(CollectionReportPdfRequest $request): StreamedResponse
    {
        return $this->report->export($request->validated(), $request->user());
    }
}
