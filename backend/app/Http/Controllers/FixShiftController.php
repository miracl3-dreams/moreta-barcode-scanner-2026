<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveFixShiftRequest;
use App\Http\Requests\SearchFixShiftRequest;
use App\Services\FixShiftService;
use Illuminate\Http\JsonResponse;

class FixShiftController extends Controller
{
    public function __construct(private FixShiftService $shifts) {}

    public function lookups(): JsonResponse
    {
        return response()->json($this->shifts->lookups());
    }

    public function search(SearchFixShiftRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->shifts->search($request->validated()),
        ]);
    }

    public function save(SaveFixShiftRequest $request): JsonResponse
    {
        $this->shifts->save($request->user(), $request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Successfully saved!',
        ]);
    }
}
