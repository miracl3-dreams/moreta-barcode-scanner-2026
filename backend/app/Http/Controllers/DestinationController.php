<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDestinationRequest;
use App\Http\Requests\UpdateDestinationRequest;
use App\Models\Destination;
use App\Services\DestinationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DestinationController extends Controller
{
    public function __construct(private DestinationService $destinations) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->destinations->list(
            trim((string) $request->query('search', '')),
            $request->query('per_page', '5'),
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'asc'),
        ));
    }

    public function store(StoreDestinationRequest $request): JsonResponse
    {
        $destination = $this->destinations->create($request->validated());

        return response()->json($destination->toListArray(), 201);
    }

    public function show(Destination $destination): JsonResponse
    {
        return response()->json($destination->toListArray());
    }

    public function update(UpdateDestinationRequest $request, Destination $destination): JsonResponse
    {
        $destination = $this->destinations->update($destination, $request->validated());

        return response()->json($destination->toListArray());
    }

    public function destroy(Destination $destination): JsonResponse
    {
        $this->destinations->delete($destination);

        return response()->json(['ok' => true]);
    }

    public function template(): StreamedResponse
    {
        return $this->destinations->template();
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->destinations->export(
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

        $result = $this->destinations->import($path);
        $status = (int) ($result['status'] ?? 200);
        unset($result['status']);

        return response()->json($result, $status);
    }
}
