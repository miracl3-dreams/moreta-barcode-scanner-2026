<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConsigneeRequest;
use App\Http\Requests\UpdateConsigneeRequest;
use App\Models\Consignee;
use App\Services\ConsigneeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConsigneeController extends Controller
{
    public function __construct(private ConsigneeService $consignees) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->consignees->list(
            trim((string) $request->query('search', '')),
            $request->query('per_page', '5'),
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'asc'),
        ));
    }

    public function store(StoreConsigneeRequest $request): JsonResponse
    {
        $consignee = $this->consignees->create($request->validated());

        return response()->json($consignee->toListArray(), 201);
    }

    public function show(Consignee $consignee): JsonResponse
    {
        return response()->json($consignee->toListArray());
    }

    public function update(UpdateConsigneeRequest $request, Consignee $consignee): JsonResponse
    {
        $consignee = $this->consignees->update($consignee, $request->validated());

        return response()->json($consignee->toListArray());
    }

    public function destroy(Consignee $consignee): JsonResponse
    {
        $this->consignees->delete($consignee);

        return response()->json(['ok' => true]);
    }

    public function pdf(): Response
    {
        return $this->consignees->pdf();
    }

    public function template(): StreamedResponse
    {
        return $this->consignees->template();
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->consignees->export(
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

        $result = $this->consignees->import($path);
        $status = (int) ($result['status'] ?? 200);
        unset($result['status']);

        return response()->json($result, $status);
    }
}
