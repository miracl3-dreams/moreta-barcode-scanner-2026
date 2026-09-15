<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeShiftScheduleRequest;
use App\Services\ShiftScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftScheduleController extends Controller
{
    public function __construct(private ShiftScheduleService $shifts) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->shifts->show($request->user()));
    }

    public function change(ChangeShiftScheduleRequest $request): JsonResponse
    {
        return response()->json($this->shifts->change($request->user(), $request->validated()));
    }
}
