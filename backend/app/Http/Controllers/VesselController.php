<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVesselRequest;
use App\Http\Requests\UpdateVesselRequest;
use App\Models\Vessel;
use App\Services\VesselService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VesselController extends Controller
{
    public function __construct(private VesselService $vessels) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->vessels->list(
            trim((string) $request->query('search', '')),
            $request->query('per_page', '5'),
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'asc'),
        ));
    }

    public function store(StoreVesselRequest $request): JsonResponse
    {
        $vessel = $this->vessels->create($request->validated());

        return response()->json($vessel->toListArray(), 201);
    }

    public function show(Vessel $vessel): JsonResponse
    {
        return response()->json($vessel->toListArray());
    }

    public function update(UpdateVesselRequest $request, Vessel $vessel): JsonResponse
    {
        $vessel = $this->vessels->update($vessel, $request->validated());

        return response()->json($vessel->toListArray());
    }

    public function destroy(Vessel $vessel): JsonResponse
    {
        $this->vessels->delete($vessel);

        return response()->json(['ok' => true]);
    }

    public function template(): StreamedResponse
    {
        return $this->vessels->template();
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->vessels->export(
            trim((string) $request->query('search', '')),
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'asc'),
        );
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:2048'],
        ]);

        $path = $request->file('file')?->getRealPath();
        if ($path === false || $path === null) {
            return response()->json(['message' => 'Unable to read uploaded file.'], 422);
        }

        $result = $this->vessels->import($path);
        $status = (int) ($result['status'] ?? 200);
        unset($result['status']);

        return response()->json($result, $status);
    }
}
