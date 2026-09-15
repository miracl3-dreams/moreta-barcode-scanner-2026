<?php

namespace App\Http\Controllers;

use App\Http\Requests\OfficialReceiptPdfRequest;
use App\Services\OfficialReceiptService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfficialReceiptController extends Controller
{
    public function __construct(private OfficialReceiptService $receipts) {}

    public function pdf(OfficialReceiptPdfRequest $request): Response
    {
        return $this->receipts->pdf($request->validated());
    }

    public function export(OfficialReceiptPdfRequest $request): StreamedResponse|Response
    {
        return $this->receipts->export($request->validated());
    }
}
