<?php

namespace App\Http\Controllers;

use App\Http\Requests\EirRegisterExportRequest;
use App\Services\EirRegisterService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EirRegisterController extends Controller
{
    public function __construct(private EirRegisterService $register) {}

    public function lookups(): JsonResponse
    {
        return response()->json($this->register->lookups());
    }

    public function export(EirRegisterExportRequest $request): StreamedResponse|JsonResponse
    {
        return $this->register->export($request->validated());
    }
}
