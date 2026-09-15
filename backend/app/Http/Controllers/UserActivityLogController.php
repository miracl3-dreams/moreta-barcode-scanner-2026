<?php

namespace App\Http\Controllers;

use App\Services\UserActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserActivityLogController extends Controller
{
    public function __construct(private UserActivityLogService $logs) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->logs->list(
            trim((string) $request->query('search', '')),
            $request->query('per_page', '5'),
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'asc'),
        ));
    }
}
