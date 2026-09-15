<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBarcodeScanRequest;
use App\Http\Requests\ScanBarcodeRequest;
use App\Services\BarcodeScannerService;
use Illuminate\Http\JsonResponse;

class BarcodeScannerController extends Controller
{
    public function __construct(private BarcodeScannerService $scanner) {}

    public function lookups(): JsonResponse
    {
        return response()->json($this->scanner->lookups());
    }

    public function scan(ScanBarcodeRequest $request): JsonResponse
    {
        return response()->json($this->scanner->scan((string) $request->validated()['code']));
    }

    public function save(SaveBarcodeScanRequest $request): JsonResponse
    {
        return response()->json($this->scanner->save($request->validated()));
    }
}
