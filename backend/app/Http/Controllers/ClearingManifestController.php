<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClearingManifestPdfRequest;
use App\Services\ClearingManifestService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClearingManifestController extends Controller
{
    public function __construct(private ClearingManifestService $manifest) {}

    public function pdf(ClearingManifestPdfRequest $request): Response
    {
        return $this->manifest->pdf(trim((string) $request->validated('voynum')));
    }

    public function export(ClearingManifestPdfRequest $request): StreamedResponse
    {
        return $this->manifest->export(trim((string) $request->validated('voynum')));
    }
}
