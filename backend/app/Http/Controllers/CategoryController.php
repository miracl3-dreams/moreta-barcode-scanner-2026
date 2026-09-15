<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CategoryController extends Controller
{
    public function __construct(private CategoryService $categories) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->categories->list(
            trim((string) $request->query('search', '')),
            $request->query('per_page', '5'),
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'asc'),
        ));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categories->create($request->validated());

        return response()->json($category->toListArray(), 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json($category->toListArray());
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category = $this->categories->update($category, $request->validated());

        return response()->json($category->toListArray());
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->categories->delete($category);

        return response()->json(['ok' => true]);
    }

    public function template(): StreamedResponse
    {
        return $this->categories->template();
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->categories->export(
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

        $result = $this->categories->import($path);
        $status = (int) ($result['status'] ?? 200);
        unset($result['status']);

        return response()->json($result, $status);
    }
}
