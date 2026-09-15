<?php

namespace App\Http\Controllers;

use App\Http\Requests\VanUsagePdfRequest;
use App\Services\VanUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VanUsageController extends Controller
{
    public function __construct(private VanUsageService $usage) {}

    public function lookups(): JsonResponse
    {
        return response()->json($this->usage->lookups());
    }

    public function searchVans(Request $request): JsonResponse
    {
        return response()->json([
            'vans' => $this->usage->searchVans(trim((string) $request->query('q', ''))),
        ]);
    }

    public function summaryPdf(VanUsagePdfRequest $request): Response
    {
        $validated = $request->validated();

        return $this->usage->summaryPdf(
            trim((string) ($validated['date_from'] ?? '')),
            trim((string) ($validated['date_to'] ?? '')),
            $request->boolean('include_soc'),
        );
    }

    public function byLocationPdf(VanUsagePdfRequest $request): Response
    {
        $validated = $request->validated();

        return $this->usage->byLocationPdf(trim((string) ($validated['origin'] ?? '')));
    }

    public function byVanNumberPdf(VanUsagePdfRequest $request): Response
    {
        $validated = $request->validated();

        return $this->usage->byVanNumberPdf(
            trim((string) ($validated['vannum'] ?? '')),
            trim((string) ($validated['date_from'] ?? '')),
            trim((string) ($validated['date_to'] ?? '')),
            true,
        );
    }

    public function historyPdf(VanUsagePdfRequest $request): Response
    {
        $validated = $request->validated();

        return $this->usage->byVanNumberPdf(
            trim((string) ($validated['vannum'] ?? '')),
            trim((string) ($validated['date_from'] ?? '')),
            trim((string) ($validated['date_to'] ?? '')),
            false,
        );
    }
}
