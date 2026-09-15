<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCheckerRequest;
use App\Http\Requests\UpdateCheckerRequest;
use App\Models\Checker;
use App\Services\CheckerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CheckerController extends Controller
{
    public function __construct(private CheckerService $checkers) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->checkers->list(
            trim((string) $request->query('search', '')),
            $request->query('per_page', '5'),
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'asc'),
        ));
    }

    public function store(StoreCheckerRequest $request): JsonResponse
    {
        $checker = $this->checkers->create($request->validated());

        return response()->json($checker->toListArray(), 201);
    }

    public function show(Checker $checker): JsonResponse
    {
        return response()->json($checker->toListArray());
    }

    public function update(UpdateCheckerRequest $request, Checker $checker): JsonResponse
    {
        $checker = $this->checkers->update($checker, $request->validated());

        return response()->json($checker->toListArray());
    }

    public function destroy(Checker $checker): JsonResponse
    {
        $this->checkers->delete($checker);

        return response()->json(['ok' => true]);
    }

    public function template(): StreamedResponse
    {
        return $this->checkers->template();
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->checkers->export(
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

        $result = $this->checkers->import($path);
        $status = (int) ($result['status'] ?? 200);
        unset($result['status']);

        return response()->json($result, $status);
    }
}
