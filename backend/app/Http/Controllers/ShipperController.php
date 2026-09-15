<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShipperRequest;
use App\Http\Requests\UpdateShipperRequest;
use App\Models\Shipper;
use App\Services\ShipperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShipperController extends Controller
{
    public function __construct(private ShipperService $shippers) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->shippers->list(
            trim((string) $request->query('search', '')),
            $request->query('per_page', '5'),
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'asc'),
        ));
    }

    public function store(StoreShipperRequest $request): JsonResponse
    {
        $shipper = $this->shippers->create($request->validated());

        return response()->json($shipper->toListArray(), 201);
    }

    public function show(Shipper $shipper): JsonResponse
    {
        return response()->json($shipper->toListArray());
    }

    public function update(UpdateShipperRequest $request, Shipper $shipper): JsonResponse
    {
        $shipper = $this->shippers->update($shipper, $request->validated());

        return response()->json($shipper->toListArray());
    }

    public function destroy(Shipper $shipper): JsonResponse
    {
        $this->shippers->delete($shipper);

        return response()->json(['ok' => true]);
    }

    public function pdf(): Response
    {
        return $this->shippers->pdf();
    }

    public function template(): StreamedResponse
    {
        return $this->shippers->template();
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->shippers->export(
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

        $result = $this->shippers->import($path);
        $status = (int) ($result['status'] ?? 200);
        unset($result['status']);

        return response()->json($result, $status);
    }
}
