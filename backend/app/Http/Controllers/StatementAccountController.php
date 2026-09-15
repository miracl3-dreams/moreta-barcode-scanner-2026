<?php

namespace App\Http\Controllers;

use App\Http\Requests\StatementAccountByScRequest;
use App\Http\Requests\StatementAccountByVoyageRequest;
use App\Http\Requests\StatementAccountOutstandingRequest;
use App\Services\StatementAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatementAccountController extends Controller
{
    public function __construct(private StatementAccountService $accounts) {}

    public function lookups(): JsonResponse
    {
        return response()->json($this->accounts->lookups());
    }

    public function byScPdf(StatementAccountByScRequest $request): Response
    {
        return $this->accounts->byScPdf($request->validated());
    }

    public function byScExport(StatementAccountByScRequest $request): StreamedResponse
    {
        return $this->accounts->byScExport($request->validated());
    }

    public function byVoyagePdf(StatementAccountByVoyageRequest $request): Response
    {
        return $this->accounts->byVoyagePdf($request->validated());
    }

    public function byVoyageExport(StatementAccountByVoyageRequest $request): StreamedResponse
    {
        return $this->accounts->byVoyageExport($request->validated());
    }

    public function byVoyageNewPdf(StatementAccountByVoyageRequest $request): Response
    {
        return $this->accounts->byVoyageNewPdf($request->validated());
    }

    public function outstandingPdf(StatementAccountOutstandingRequest $request): Response
    {
        return $this->accounts->outstandingPdf($request->validated());
    }

    public function outstandingExport(StatementAccountOutstandingRequest $request): StreamedResponse
    {
        return $this->accounts->outstandingExport($request->validated());
    }
}
