<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePrefixRequest;
use App\Http\Requests\UpdatePrefixRequest;
use App\Models\Prefix;
use App\Services\PrefixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrefixController extends Controller
{
    public function __construct(private PrefixService $prefixes) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->prefixes->list(
            trim((string) $request->query('search', '')),
            $request->query('per_page', '5'),
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'asc'),
        ));
    }

    public function store(StorePrefixRequest $request): JsonResponse
    {
        $prefix = $this->prefixes->create($request->validated());

        return response()->json($prefix->toListArray(), 201);
    }

    public function show(Prefix $prefix): JsonResponse
    {
        return response()->json($prefix->toListArray());
    }

    public function update(UpdatePrefixRequest $request, Prefix $prefix): JsonResponse
    {
        $prefix = $this->prefixes->update($prefix, $request->validated());

        return response()->json($prefix->toListArray());
    }

    public function destroy(Prefix $prefix): JsonResponse
    {
        $this->prefixes->delete($prefix);

        return response()->json(['ok' => true]);
    }

    public function template(): StreamedResponse
    {
        return $this->prefixes->template();
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->prefixes->export(
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

        $result = $this->prefixes->import($path);
        $status = (int) ($result['status'] ?? 200);
        unset($result['status']);

        return response()->json($result, $status);
    }
}
