<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillingRegisterExportRequest;
use App\Services\BillingRegisterService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingRegisterController extends Controller
{
    public function __construct(private BillingRegisterService $register) {}

    public function lookups(): JsonResponse
    {
        return response()->json($this->register->lookups());
    }

    public function export(BillingRegisterExportRequest $request): StreamedResponse|JsonResponse
    {
        return $this->register->export($request->validated());
    }
}
