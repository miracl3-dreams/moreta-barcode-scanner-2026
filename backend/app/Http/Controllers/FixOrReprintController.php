<?php

namespace App\Http\Controllers;

use App\Http\Requests\AllowOrReprintRequest;
use App\Services\FixOrReprintService;
use Illuminate\Http\JsonResponse;

class FixOrReprintController extends Controller
{
    public function __construct(private FixOrReprintService $reprints) {}

    public function allow(AllowOrReprintRequest $request): JsonResponse
    {
        $this->reprints->allow($request->user(), $request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Successfully Updated!',
        ]);
    }
}
