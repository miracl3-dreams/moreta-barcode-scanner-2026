<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePrinterAdjustmentRequest;
use App\Services\PrinterAdjustmentService;
use Illuminate\Http\JsonResponse;

class PrinterAdjustmentController extends Controller
{
    public function __construct(private PrinterAdjustmentService $settings) {}

    public function show(): JsonResponse
    {
        return response()->json($this->settings->show());
    }

    public function save(SavePrinterAdjustmentRequest $request): JsonResponse
    {
        $saved = $this->settings->save($request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Settings Saved',
            'bol_top' => $saved['bol_top'],
            'bol_left' => $saved['bol_left'],
            'or_top' => $saved['or_top'],
            'or_left' => $saved['or_left'],
            'rep_top' => $saved['rep_top'],
            'rep_left' => $saved['rep_left'],
        ]);
    }
}
