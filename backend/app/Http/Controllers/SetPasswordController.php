<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSetPasswordRequest;
use App\Services\SetPasswordService;
use Illuminate\Http\JsonResponse;

class SetPasswordController extends Controller
{
    public function __construct(private SetPasswordService $passwords) {}

    public function update(UpdateSetPasswordRequest $request): JsonResponse
    {
        $this->passwords->update($request->user(), $request->validated());

        return response()->json(['ok' => true]);
    }
}
