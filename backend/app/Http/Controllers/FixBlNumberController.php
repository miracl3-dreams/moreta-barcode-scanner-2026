<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReplaceBlNumberRequest;
use App\Services\FixBlNumberService;
use Illuminate\Http\JsonResponse;

class FixBlNumberController extends Controller
{
    public function __construct(private FixBlNumberService $numbers) {}

    public function replace(ReplaceBlNumberRequest $request): JsonResponse
    {
        $this->numbers->replace($request->user(), $request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Successfully Updated!',
        ]);
    }
}
