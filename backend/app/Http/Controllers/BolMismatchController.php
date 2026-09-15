<?php

namespace App\Http\Controllers;

use App\Http\Requests\FixBolMismatchRequest;
use App\Http\Requests\FixMissingBolMismatchRequest;
use App\Http\Requests\SearchBolMismatchRequest;
use App\Services\BolMismatchService;
use Illuminate\Http\JsonResponse;

class BolMismatchController extends Controller
{
    public function __construct(private BolMismatchService $mismatch) {}

    public function search(SearchBolMismatchRequest $request): JsonResponse
    {
        $voynum = trim((string) $request->validated('voynum'));

        return response()->json([
            'data' => $this->mismatch->searchMismatch($voynum),
        ]);
    }

    public function searchMissing(SearchBolMismatchRequest $request): JsonResponse
    {
        $voynum = trim((string) $request->validated('voynum'));

        return response()->json([
            'data' => $this->mismatch->searchMissing($voynum),
        ]);
    }

    public function fix(FixBolMismatchRequest $request): JsonResponse
    {
        $recids = array_map('intval', $request->validated('recids'));

        return response()->json($this->mismatch->fixMismatch($recids, $request->user()));
    }

    public function fixMissing(FixMissingBolMismatchRequest $request): JsonResponse
    {
        $recids = array_map('intval', $request->validated('recids'));

        return response()->json($this->mismatch->fixMissing($recids, $request->user()));
    }
}
