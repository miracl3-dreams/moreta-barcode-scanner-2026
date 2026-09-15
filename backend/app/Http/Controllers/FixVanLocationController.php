<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReplaceVanLocationRequest;
use App\Services\FixVanLocationService;
use Illuminate\Http\JsonResponse;

class FixVanLocationController extends Controller
{
    public function __construct(private FixVanLocationService $locations) {}

    public function lookups(): JsonResponse
    {
        return response()->json($this->locations->lookups());
    }

    public function replace(ReplaceVanLocationRequest $request): JsonResponse
    {
        $this->locations->replace($request->user(), $request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Successfully Updated!',
        ]);
    }
}
