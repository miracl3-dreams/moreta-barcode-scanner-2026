<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBankRequest;
use App\Http\Requests\UpdateBankRequest;
use App\Models\Bank;
use App\Services\BankService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankController extends Controller
{
    public function __construct(private BankService $banks) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->banks->list(
            trim((string) $request->query('search', '')),
            $request->query('per_page', '5'),
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'asc'),
        ));
    }

    public function store(StoreBankRequest $request): JsonResponse
    {
        $bank = $this->banks->create($request->validated());

        return response()->json($bank->toListArray(), 201);
    }

    public function show(Bank $bank): JsonResponse
    {
        return response()->json($bank->toListArray());
    }

    public function update(UpdateBankRequest $request, Bank $bank): JsonResponse
    {
        $bank = $this->banks->update($bank, $request->validated());

        return response()->json($bank->toListArray());
    }

    public function destroy(Bank $bank): JsonResponse
    {
        $this->banks->delete($bank);

        return response()->json(['ok' => true]);
    }

    public function template(): StreamedResponse
    {
        return $this->banks->template();
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->banks->export(
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

        $result = $this->banks->import($path);
        $status = (int) ($result['status'] ?? 200);
        unset($result['status']);

        return response()->json($result, $status);
    }
}
