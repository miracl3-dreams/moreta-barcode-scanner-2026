<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewVanInventoryRequest;
use App\Services\VanInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class VanInventoryController extends Controller
{
    public function __construct(private VanInventoryService $inventory) {}

    public function lookups(): JsonResponse
    {
        return response()->json($this->inventory->lookups());
    }

    public function preview(PreviewVanInventoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json($this->inventory->preview(
            trim((string) ($validated['vantype'] ?? '')),
            trim((string) ($validated['sort_by'] ?? '')),
            trim((string) ($validated['sort_dir'] ?? 'ASC')),
        ));
    }

    public function pdf(PreviewVanInventoryRequest $request): Response
    {
        $validated = $request->validated();

        return $this->inventory->pdf(
            trim((string) ($validated['vantype'] ?? '')),
            trim((string) ($validated['sort_by'] ?? '')),
            trim((string) ($validated['sort_dir'] ?? 'ASC')),
        );
    }
}
