<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListFixBlOrderRequest;
use App\Http\Requests\SaveFixBlOrderRequest;
use App\Services\FixBlOrderService;
use Illuminate\Http\JsonResponse;

class FixBlOrderController extends Controller
{
    public function __construct(private FixBlOrderService $orders) {}

    public function index(ListFixBlOrderRequest $request): JsonResponse
    {
        return response()->json($this->orders->list(trim((string) $request->validated('voynum'))));
    }

    public function save(SaveFixBlOrderRequest $request): JsonResponse
    {
        $this->orders->save($request->user(), $request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Finished!',
        ]);
    }
}
