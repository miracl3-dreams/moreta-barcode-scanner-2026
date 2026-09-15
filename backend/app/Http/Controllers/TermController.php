<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTermRequest;
use App\Http\Requests\UpdateTermRequest;
use App\Models\Term;
use App\Services\TermService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TermController extends Controller
{
    public function __construct(private TermService $terms) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->terms->list(
            trim((string) $request->query('search', '')),
            $request->query('per_page', '5'),
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'asc'),
        ));
    }

    public function store(StoreTermRequest $request): JsonResponse
    {
        $term = $this->terms->create($request->validated());

        return response()->json($term->toListArray(), 201);
    }

    public function show(Term $term): JsonResponse
    {
        return response()->json($term->toListArray());
    }

    public function update(UpdateTermRequest $request, Term $term): JsonResponse
    {
        $term = $this->terms->update($term, $request->validated());

        return response()->json($term->toListArray());
    }

    public function destroy(Term $term): JsonResponse
    {
        $this->terms->delete($term);

        return response()->json(['ok' => true]);
    }

    public function template(): StreamedResponse
    {
        return $this->terms->template();
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->terms->export(
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

        $result = $this->terms->import($path);
        $status = (int) ($result['status'] ?? 200);
        unset($result['status']);

        return response()->json($result, $status);
    }
}
