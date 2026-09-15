<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContainerRequest;
use App\Http\Requests\UpdateContainerRequest;
use App\Models\Container;
use App\Services\ContainerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContainerController extends Controller
{
    public function __construct(private ContainerService $containers) {}

    public function lookups(): JsonResponse
    {
        return response()->json($this->containers->lookups());
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->containers->list(
            trim((string) $request->query('search', '')),
            $request->query('per_page', '5'),
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'asc'),
        ));
    }

    public function store(StoreContainerRequest $request): JsonResponse
    {
        $container = $this->containers->create($request->validated());

        return response()->json($this->containers->toListArray($container), 201);
    }

    public function show(Container $container): JsonResponse
    {
        return response()->json($this->containers->show($container));
    }

    public function update(UpdateContainerRequest $request, Container $container): JsonResponse
    {
        $container = $this->containers->update($container, $request->validated());

        return response()->json($this->containers->toListArray($container));
    }

    public function destroy(Container $container): JsonResponse
    {
        $this->containers->delete($container);

        return response()->json(['ok' => true]);
    }

    public function template(): StreamedResponse
    {
        return $this->containers->template();
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->containers->export(
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

        $result = $this->containers->import($path);
        $status = (int) ($result['status'] ?? 200);
        unset($result['status']);

        return response()->json($result, $status);
    }
}
