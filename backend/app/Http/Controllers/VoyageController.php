<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVoyageRequest;
use App\Http\Requests\UpdateSailingDateRequest;
use App\Models\Voyage;
use App\Services\VoyageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoyageController extends Controller
{
    public function __construct(private VoyageService $voyages) {}

    public function lookups(Request $request): JsonResponse
    {
        return response()->json($this->voyages->lookups($request->user()));
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->voyages->list(
            trim((string) $request->query('search', '')),
            $request->query('per_page', '5'),
        ));
    }

    public function store(StoreVoyageRequest $request): JsonResponse
    {
        return response()->json($this->voyages->create($request->validated(), $request->user()), 201);
    }

    public function show(Voyage $voyage): JsonResponse
    {
        return response()->json($this->voyages->show($voyage));
    }

    public function destroy(Voyage $voyage, Request $request): JsonResponse
    {
        $this->voyages->delete($voyage, $request->user());

        return response()->json(['ok' => true]);
    }

    public function updateSailingDate(UpdateSailingDateRequest $request, Voyage $voyage): JsonResponse
    {
        return response()->json($this->voyages->updateSailingDate(
            $voyage,
            $request->validated(),
            $request->user(),
        ));
    }
}
