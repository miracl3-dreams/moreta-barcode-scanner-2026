<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(private ExpenseService $expenses) {}

    public function lookups(Request $request): JsonResponse
    {
        return response()->json($this->expenses->lookups($request->user()));
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->expenses->list(
            trim((string) $request->query('search', '')),
            $request->query('per_page', '5'),
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'asc'),
        ));
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenses->create($request->validated(), $request->user());

        return response()->json($expense->toListArray(), 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        return response()->json($expense->toListArray());
    }

    public function update(StoreExpenseRequest $request, Expense $expense): JsonResponse
    {
        $expense = $this->expenses->update($expense, $request->validated(), $request->user());

        return response()->json($expense->toListArray());
    }

    public function destroy(Expense $expense, Request $request): JsonResponse
    {
        $this->expenses->delete($expense, $request->user());

        return response()->json(['ok' => true]);
    }
}
